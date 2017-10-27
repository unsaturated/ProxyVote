<?php
/*
Proxy Vote is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Proxy Vote is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Proxy Vote. If not, see 
https://github.com/unsaturated/proxyvote/blob/master/LICENSE.
*/

// Get the posted event number to use in the file name
$eventid = $_POST['event'];
if(empty($eventid)) $eventid = "";

header("Content-type:text/xml");
header("Content-Disposition:attachment;filename=event$eventid.xml");
echo stripslashes($_POST['export_xml']);
?>
