<?php 

function createDienstBesprechung (array $PARAM, mysqli $conn)
{
	$rnd = rand(0,32768);
	?>
	<form class="db_options" method="POST" action="" onsubmit="callFunction(this,'createDB','message'); return false;">
		<div class="edit_wrapper<?php echo($_addclasses); ?>">
			<label class="date" for="db_<?php echo($key.$rnd); ?>">Datum der DB</label>
			<input name="datumdb" id="datumdb_<?php echo($rnd); ?>" type="date" value="<?php echo(date()); ?>" />
			<div class="clear"></div>
		</div>
	</form>
	<?php
}

?>
