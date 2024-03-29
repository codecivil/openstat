<?php
//Settings for the openStat server instance

//Enable debug (see core/data/debugdata.php for path and filename settings)
//$_SESSION['DEBUG'] = true;

//Maximal number of results to be displayes in the details pane
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
?>
