<?php 
//namespace OpenStat;

//interface instead of class?
class OpenStatAuth {
	
	public $user = "";
	public $passwd = "";
	public $connection;// = new mysqli;
	protected $admin;
	public $admin_passwd;
	
	public function __construct(string $user, string $passwd, mysqli $connection) {
		$this->user = $this->_input_escape($user);
		$this->passwd = $this->_input_escape($passwd);		
		$this->connection = $connection;
		$this->admin = ''; //$_SESSION['os_user'];
		$this->admin_passwd = ""; //get this from user
	}
	
	public function login() {
		//generate SESSION variables and cookies
		$_return = array();
		$_stmt_array = array();
		//test if mysql can be decrypted
		$_stmt_array['stmt'] = "SELECT id FROM os_roles";
		$_result_array = _execute_stmt($_stmt_array,$this->connection); $_result=$_result_array['result'];
		if ( ! $_result ) {  $_return['error'] = "Die Datenbank ist nicht freigeschaltet."; return $_return; }
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT id,roleid,pwdhash FROM os_users WHERE username = ?";
		$_stmt_array['str_types'] = "s";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $this->user;
		$_result_array = _execute_stmt($_stmt_array,$this->connection); $_result=$_result_array['result'];
		$_result_user = array();
		if ( $_result AND $_result->num_rows > 0 ) {
			while ($row=$_result->fetch_assoc()) {
				foreach ($row as $key=>$value) {
					$_result_user[$key] = $value;
				}
				$_roleid = $_result_user['roleid'];
			}
		}
		unset($_stmt_array); $_stmt_array = array();
		$_stmt_array['stmt'] = "SELECT rolename,parentid FROM os_roles WHERE id = ?";
		$_stmt_array['str_types'] = "i";
		$_stmt_array['arr_values'] = array();
		$_stmt_array['arr_values'][] = $_roleid;
		$_result_array = _execute_stmt($_stmt_array,$this->connection); $_result=$_result_array['result'];
		if ( $_result AND $_result->num_rows > 0 ) {
			while ($row=$_result->fetch_assoc()) {
				foreach ($row as $key=>$value) {
					$_result_user[$key] = $value;
				}
				$_rolename = $_result_user['rolename'];
				$_parentid = $_result_user['parentid'];
				unset($_stmt_array); $_stmt_array = array();
				$_stmt_array['stmt'] = "SELECT rolename FROM os_roles WHERE id = ?";
				$_stmt_array['str_types'] = "i";
				$_stmt_array['arr_values'] = array();
				$_stmt_array['arr_values'][] = $_parentid;
				$_parentname = execute_stmt($_stmt_array,$this->connection)['result']['rolename'][0];
			}
		}		
		if (!$_result_user['pwdhash']) { $_return['error'] = "Benutzer oder Passwort ist falsch."; return $_return; }
		if (sodium_crypto_pwhash_str_verify($_result_user['pwdhash'],$this->passwd)) {
			$_SESSION['os_user'] = $_result_user['id'];
			$_SESSION['os_username'] = $this->user;
			$_SESSION['os_role'] = $_roleid;
			$_SESSION['os_rolename'] = $_rolename;
			$_SESSION['os_parent'] = $_parentid;
			$_SESSION['os_parentname'] = $_parentname;
			unset($_stmt_array); $_stmt_array = array();
			$_stmt_array['stmt'] = "SELECT password,salt,nonce FROM os_passwords WHERE userid = ?"; 
			$_stmt_array['str_types'] = "i";
			$_stmt_array['arr_values'] = array();
			$_stmt_array['arr_values'][] = $_result_user['id'];
			$_result_array = _execute_stmt($_stmt_array,$this->connection); $_result=$_result_array['result'];
			unset($_result_user);
			$_result_user = array();
			if ( $_result AND $_result->num_rows > 0 ) {
				while ($row=$_result->fetch_assoc()) {
					foreach ($row as $key=>$value) {
						$_result_user[$key] = sodium_hex2bin($value);
					}
					$_SESSION['os_dbpwd'] = sodium_crypto_secretbox_open($_result_user['password'],$_result_user['nonce'],$this->gen_key($_result_user['salt'])['key']);
				}
				if (! isset($_SESSION['os_dbpwd']) ) { $_return['error'] = "Benutzer oder Passwort ist falsch."; }; //this is not correct; test for NULL?
			}
			
		} else {
			$_return['error'] = "Benutzer oder Passwort ist falsch."; 
		}
		return $_return;
	}

	public function logout() { session_start(); session_destroy(); }
		
	public function new_user() {}

	protected function _store_hash_in_db() {}

	protected function _store_encrypted_password() {}
	//searches password table for password of all tables in _SESSION['tables'] encrypted with $admin_password
	//decrypts it, and reencrypts it using $password, storing it under $username and $table.
	//$admin and $admin_passwd are read from session variable

	protected function _input_escape(string $inputstring) { return $inputstring; } //do later

	public function gen_hash() {
			return sodium_crypto_pwhash_str($this->passwd,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
	}

	public function gen_key($salt) {
			if (! isset($salt) ) { $salt=random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES); }
//			$iterations=1024000+random_int(0,512000);
//			return array( "key" => hash_pbkdf2("sha256sum", $this->passwd, $salt, $iterations), "salt" => $salt, "iterations" => $iterations );
			return array( "key" => sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$this->passwd,$salt,SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE), "salt" => $salt);
	}

	
}

//openStat authorization module


?>
