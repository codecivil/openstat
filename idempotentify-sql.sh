#!/bin/bash
SQL="$1"
while read _line; do
	if [[ "$_line" == "INSERT "*"INTO "* ]]; then
		_table="$(echo $_line | sed 's/INSERT[ ]*INTO //; s/ .*//; s/`//g')"
		_keys="$(echo $_line | sed "s/INSERT[ ]*INTO $_table //; s/VALUES .*//; s/.*(//; s/).*//; s/\`/'/g; s/','/' '/g")"
		_values="$(echo $_line | sed "s/.*VALUES (//; s/)\;.*//;s/','/' '/g")"
		# make an array out of them
		eval _keys=($_keys)
		eval _values=($_values)
		#
		case "$_table" in
			*"_permissions"*)
				_key="keymachine"
				_addcond="(SELECT count("'*'") FROM information_schema.COLUMNS WHERE TABLE_NAME = '$_table' AND COLUMN_NAME = '\$_value') > 0 AND "
				;;
			*"_references"*)
				echo "$_line"
				continue
				## references are harder to distinguish (several entries with same referencetag is possible and wanted) and
				## double entries do not hurt really 
				_key="referencetag"
				_addcond=""
				;;
			*"_functions"*)
				_key="functionmachine"
				_addcond=""
				;;
		esac
		index=0
		while [[ "${_keys[$index]}" != "$_key" && "${_keys[$index]}" != "" ]]; do index++; done
		_value="${_values[$index]}"
		_addcond="$(echo $_addcond | sed "s/\$_value/$_value/")"
		_idem="FROM DUAL WHERE $_addcond (SELECT count($_key) FROM \`${_table}\` where $_key = '$_value') = 0;";
		echo "$_line" | sed "s/VALUES (/SELECT /; s/)\;/ $_idem/"
	else
		echo "$_line"
	fi
done < $SQL > ${SQL%sql}idempotent.sql
exit 0
