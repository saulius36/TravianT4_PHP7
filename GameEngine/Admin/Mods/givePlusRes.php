<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       mainteneceBan.php                                           ##
##  Developed by:  aggenkeech                                                  ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2012. All rights reserved.                ##
##                                                                             ##
#################################################################################

include_once("../../config.php");
include_once("../../Database.php"); 

$session = $_POST['admid'];

$sql = mysql_query("SELECT * FROM ".TB_PREFIX."users WHERE id = ".$session."");
$access = mysql_fetch_array($sql);
$sessionaccess = $access['access'];

if($sessionaccess != 9) die("<h1><font color=\"red\">Access Denied: You are not Admin!</font></h1>");

$wood = $_POST['wood'] * 86400;
$clay = $_POST['clay'] * 86400;
$iron = $_POST['iron'] * 86400;
$crop = $_POST['crop'] * 86400;

$query = "SELECT * FROM ".TB_PREFIX."users";
$result = mysql_query($query);
while($row = mysql_fetch_assoc($result))
{
	if($row['b1'] < time()) { $b1before = time(); $addb1 = $b1before + $wood; } elseif($row['b1'] > time()) { $b1before = $row['b1']; $addb1 = $b1before + $wood; }
	if($row['b2'] < time()) { $b2before = time(); $addb2 = $b1before + $clay; } elseif($row['b2'] > time()) { $b2before = $row['b2']; $addb2 = $b1before + $clay; }
	if($row['b3'] < time()) { $b3before = time(); $addb3 = $b1before + $iron; } elseif($row['b3'] > time()) { $b3before = $row['b3']; $addb3 = $b1before + $iron; }
	if($row['b4'] < time()) { $b4before = time(); $addb4 = $b1before + $crop; } elseif($row['b4'] > time()) { $b4before = $row['b4']; $addb4 = $b1before + $crop; }
	mysql_query("UPDATE ".TB_PREFIX."users SET
		b1 = '".$addb1."', 
		b2 = '".$addb2."', 
		b3 = '".$addb3."', 
		b4 = '".$addb4."' 
		WHERE id = '".$row['id']."'");
}

header("Location: ../../../Admin/admin.php?p=givePlusRes&g");
?>