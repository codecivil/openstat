<?php
//Settings for the openStat server instance

//Enable debug (see core/data/debugdata.php for path and filename settings)
//$_SESSION['DEBUG'] = true;

//Default page size for results if no value was set as yet by the user
//Limited by max_results
$_SESSION['paging_default'] = 100;

//Maximal number of results to be displayed in the details pane
//The statistics pane counts all results independently of this setting
//The user may reduce this limit but cannot exceed it
$_SESSION['max_results'] = 1000;

//Maximal number of results of a filter query not to trigger the measurement of execution rates.
//If, based on this rate, the execution is estimated to take longer than max_execution_time 
//in php.ini the generation of a results table is skipped.
//This threshold should be set to a reasonable high number of results which is still safe to cause no
//trouble. If the number is too low, the higher variability of the execution rate may cause a skip too early.
//If set to 0, the forecast is always active.
$_SESSION['exec_forecast_threshold'] = 0;

//Additional JS files can be loaded via an array of filenames, e.g. instance specific. These are to be located in public/js/
//for instance distributions or v1.0/vendor/js/ for vendor specific files, e.g.
//$_SESSION['additional_js'] = array("vendor_functions.js");
?>
