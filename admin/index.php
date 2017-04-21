<?php
include('../common.php');

/* actions */
define('ACTION_NONE',     0);
define('ACTION_UPDATE',   1);
define('ACTION_DELETE',   2);
define('ACTION_GET',      3);
define('ACTION_MAKELIVE', 4);

include('actions/helpers.php');
include('actions/update.php');
include('actions/delete.php');
include('actions/get.php');
include('actions/live.php');
    
/* default post/get values */
if (!isset ($_GET['action']))     $_GET['action'] = ACTION_NONE;
if (!isset ($_GET['id']))         $_GET['id'] = NULL;
if (!isset ($_POST['id']))        $_POST['id'] = NULL;
if (!isset ($_POST['name']))      $_POST['name'] = NULL;
if (!isset ($_POST['startDate'])) $_POST['startDate'] = date("Y-m-d");
if (!isset ($_POST['stopDate']))  $_POST['stopDate'] = NULL;
if (!isset ($_POST['duration']))  $_POST['duration'] = 10;

/* general user error message */
$USERMSG_TYPE='sucess';
$USERMSG_STR = "";

/* database connection */
$db = @mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PW); 
if (!$db)  die('Could not connect: ' . mysql_error());
if(!mysql_select_db(MYSQL_DB,$db)) die('Could not connect db: ' . mysql_error());

/* main action switch */
$ShowEditPanel = false;
switch($_GET['action']) {
    case ACTION_NONE:
        break;
    case ACTION_UPDATE:
        if (doUpdate()) endOk();
        break;
    case ACTION_DELETE: 
        if(doDelete()) endOk();
        break;
    case ACTION_MAKELIVE: 
        if (doLive()) endOk();
        break;        
    case ACTION_GET:
        $ShowEditPanel = true;
        doGet();
        break;
}

/* Take opportunity to update asset satus*/
updateAssetsStatus();

/* count nb of entry */
$sql = 'SELECT COUNT(*) FROM `'.MYSQL_TABLE_ASSETS.'`';
$req = @mysql_query($sql) or sqldie($sql);
$row = mysql_fetch_assoc($req);
$count = $row['COUNT(*)'];
if (!$ShowEditPanel) $ShowEditPanel = ($count == 0);
?>

<html>
<link rel="stylesheet" href="css/html.css">
<link rel="stylesheet" href="css/form.css">
<link rel="stylesheet" href="css/table.css">
<link rel="stylesheet" href="css/a.css">
<link rel="stylesheet" href="css/file.css">
<link rel="stylesheet" href="css/usermsg.css">
<link rel="stylesheet" href="css/display_status.css">
<script src="js/helpers.js"></script>
<body>

<!-- ERROR MSG -->
<?php if (!empty($USERMSG_STR)) {
    echo "<div id='usermsg' class='$USERMSG_TYPE'>$USERMSG_STR</div>\n";
    echo "<script>\n";
    echo "setTimeout(function() { document.getElementById('usermsg').className += ' load'; }, 100)\n";
    echo "setTimeout(function() { document.getElementById('usermsg').className += ' unload'; }, 3000)\n";
    echo "</script>\n";
}
?>

<h1>Admin Page</h1>
<div id="displayStatus">Connection...</div>
<script>
updateDisplayStatus('displayStatus');
setInterval(function(){ updateDisplayStatus('displayStatus'); }, 5000);
</script>

<!-- EDIT PANEL -->
<?php
if ($count != 0) {
    echo "<a href='#' onclick=\"toggleVisibiltiy('formContainer'); return false;\">Edit Panel</a>\n";
}
?>
<div id='formContainer' style='display:<?php echo ($ShowEditPanel?'box':'none'); ?>'>
<form id="form" action='index.php?action=<?php echo ACTION_UPDATE; ?>' method='post' enctype="multipart/form-data">
   
    <input type="text"   name="id"        value="<?php echo $_POST['id'];?>" style="display:none;" />    

    <label for="assetFile">Asset file <i>(Zipped HTML page, JPEG or MPEG4 file)</i>:</label>
    <div class="file-area">
        <input type="file" name="assetFile" onchange="onAssetFile(this.value,'file-dummy')" />
        <div id='file-dummy'>Please select an asset file</div>
    </div>
    
    <label for="name">Name <i>(optional)</i>:</label>
    <input type="text"   name="name"      value="<?php echo $_POST['name'];?>" autofocus/>    
    
    <label for="startDate">Start Date:</label>
    <input type="date"   name="startDate" value="<?php echo $_POST['startDate'];?>" />    
    
    <label for="stopDate">Stop Date: <i>(not defined=infinite)</i></label>
    <input type="date"   name="stopDate"  value="<?php echo $_POST['stopDate'];?>" />
    
    <label for="duration">Duration in second: <i>(0 = no automatic end)</i></label>
    <input type="number" name="duration"  value="<?php echo $_POST['duration'];?>" />
    
    <input type="submit" value="Submit">

</form>
</div>

<!-- RESULT TABLE -->
<?php
if ($count != 0)
{
    /* by default sort by id */
    $sortBy = 'id';
    $sortAsc = 0;     
    if (isset ($_GET['sortBy'])) $sortBy = $_GET['sortBy'];
    if (isset ($_GET['sortAsc'])) $sortAsc = $_GET['sortAsc'];
    $newSortAsc = ($sortAsc)?0:1;
    
    echo "<table>\n";

    /* table header */
    echo "<tr>\n";
    echo "<th onclick=\"location.href='index.php?sortBy=id&sortAsc=$newSortAsc'\">id</th>\n";        
    echo "<th onclick=\"location.href='index.php?sortBy=name&sortAsc=$newSortAsc'\">name</th>\n";        
    echo "<th onclick=\"location.href='index.php?sortBy=startDate&sortAsc=$newSortAsc'\">startDate</th>\n";        
    echo "<th onclick=\"location.href='index.php?sortBy=stopDate&sortAsc=$newSortAsc'\">stopDate</th>\n";        
    echo "<th onclick=\"location.href='index.php?sortBy=duration&sortAsc=$newSortAsc'\">duration</th>\n";        
    echo "<th onclick=\"location.href='index.php?sortBy=assetId&sortAsc=$newSortAsc'\">assetId</th>\n";        
    echo "<th onclick=\"location.href='index.php?sortBy=status&sortAsc=$newSortAsc'\">status</th>\n";        
    echo "<th></th>\n";    
    echo "</tr>\n";    

    /* table contents */
    $sql = 'SELECT * FROM `'.MYSQL_TABLE_ASSETS.'` ORDER BY `'.$sortBy.'` '.($sortAsc?'ASC':'DESC');
    $req = @mysql_query($sql) or sqldie($sql);
    while ($row = mysql_fetch_assoc($req)) { 
        echo "<tr onclick=\"location.href='index.php?action=".ACTION_GET."&id=".$row['id']."';\">\n";    
        foreach ($row as $key => $value) {
            echo "<td>";
            if ($key=='status') {
                switch($value) {
                    case STATUS_PENDING:   echo "<span style='color:blue'>pending</span>"; break;
                    case STATUS_LIVE: echo "<span style='color:red'>LIVE</span>"; break;
                    case STATUS_FINISHED:  echo "*finished*"; break;
                    default: echo ""; break;
                }
            }else {
                echo $value;
            }
            echo "</td>\n";
        }
        echo "<td>";
        echo "<a href='index.php?action=".ACTION_DELETE."&id=".$row['id']."'>Delete</a>\n";   
        echo "<a href='../assets/".$row['assetId']."/index.php?debug=1&t=".$row['duration']."'>Test</a>\n";
        if ($row['status']==STATUS_BACKSTAGE) echo "<a href='index.php?action=".ACTION_MAKELIVE."&id=".$row['id']."'>Make Live</a>\n";
        echo "</td>\n";
        echo "</tr>\n";
    } 

    echo "</table>\n";
}
?>

</body>
</html>