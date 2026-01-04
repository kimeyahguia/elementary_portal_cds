<!-- manually pa lang ang pag end ng session
 since wala pa logout button hehe, ex: "localhost/cdsportal/logout.php" 
 -->

<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit();