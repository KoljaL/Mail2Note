<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// CONFIG
$hostname       = '{mail.server.com:143}INBOX';
$username       = 'notes@domain.de';
$password       = 'ewwfwfwefwefwe';
$new_entry_key  = 'xxx';
$signature      = '';

// PRESETS & CONNECT
$wrong_key     = '';
$wrong_key_css = '';
$checked       = '';
$inbox         = imap_open($hostname, $username, $password) or die('Cannot connect to server: ' . imap_last_error());
$emails        = imap_search($inbox, 'ALL');
$message       = '';
$data_mail     = array();
$data_file     = array();
$data_combined = array();

// NEW ENTRY
if (isset($_POST['key'])){
    if($_POST['key'] == $new_entry_key){
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: myself";
        mail($username, $_POST['topic'], $_POST['items'], $headers);

    }
    elseif(isset($_POST['key'])){
        $wrong_key     = "false ";
        $wrong_key_css = ".new_entry input.key::placeholder{color: #a6001a;}";
        $checked       = "checked";
    }
} 

// READ UNREAD MAILS
if ($emails) {
    // echo count($emails);
    $output = '';
    rsort($emails);
    foreach ($emails as $email_number) {
        
        $overview   = imap_fetch_overview($inbox, $email_number, 0);
        $structure  = imap_fetchstructure($inbox, $email_number);
        $headerinfo = imap_headerinfo($inbox, $email_number);


        if (!isset($_GET['read'])){
            if ($overview[0]->seen == 1 ) {continue;}
        }

        // get message
        $message = imap_fetchbody($inbox, $email_number, 1);
        // pprint($message);        
        // format messagetext (linebreaks)
        $message = imap_qprint($message);
        // pprint($message);        
        // each line becomes a value in array
        $message = preg_split('/\n|\r\n?/', $message);
        // pprint($message);        
        // remove empty rows & signature
        $message = array_filter($message);
        $signature_key = (array_search($signature, $message));
        if($signature_key){
            unset($message[$signature_key]);
        }
        // pprint($message);


        // MAKE SUBJECT (TOPIC) AND DATE
        $subject    = utf8_decode(imap_utf8($overview[0]->subject));    
        $date       = utf8_decode(imap_utf8($headerinfo->MailDate)); // date with headerinfo->MailDate, timezone, but breaks maybe in some cases: "Double timezone specification"
        $date       = new DateTime($date);
        $date       = $date->format("d M Y H:i"); 


        // every item gets parameters
        // reset items array
        $items = array();
        foreach ($message as $nr => $item) {
            $id = md5($item.$subject);
            $id = preg_replace('/[0-9]+/', '', $id);
            $id = substr($id, 0, 5);
            $items[$id] = array('id' => $id,'name' => $item, 'date'=> $date, 'done'=> 0, 'del'=> 0, 'imp'=> 0);
        }   

        // all items belongs to one subject
        if(!empty($subject) and !empty($items)){
            $data_mail  =  array_merge_recursive($data_mail, array($subject => $items));
        }
   }    
}
imap_errors();
imap_alerts();
imap_close($inbox);
 

// get existing data from file
$data_file = json_decode(file_get_contents('notes.json'),true);
// echo 'sizeof($data_file) '.sizeof($data_file).'<br>';


if( (sizeof($data_file)==0) and (sizeof($data_mail)>1) ){
    // echo "empty";
    $data_mail_j = json_encode($data_mail);
    // echo $data_mail;
    file_put_contents('notes.json',$data_mail_j);
    $data_file = json_decode(file_get_contents('notes.json'),true);
}

// combine both array and delete duplicates
if( (sizeof($data_mail)>0) or (sizeof($data_file)>0) ){
    $data_combined = array_merge_recursive($data_file,$data_mail);
}

// debug output
if(sizeof($data_combined) < 1){
    echo "no data found<br>";
    echo '$data_mail:';
    pprint($data_mail,0,1);
    echo '$data_file:';
    pprint($data_file,0,1);
    echo '$data_combined:';
    pprint($data_combined,'data_combined',0,1);
}


// if duplicate take the first value, right?
foreach ($data_combined as $topic => $item) {
    foreach ($item as $key => $value) {
        foreach ($value as $parameter => $param) {
            if(is_array($param)){ 
                $data_combined[$topic][$key][$parameter] = $data_combined[$topic][$key][$parameter][0];
            }
        }
    }
}
// delete entry
if (isset($_POST['del'])){
    $del = $_POST['del'];
    foreach ($data_combined as $key => $value) {
        if(isset($data_combined[$key][$del])){ 
            unset($data_combined[$key][$del]);
        } 
    }
} 
// mark entry as done
if (isset($_POST['done'])){
    $param = $_POST['done'];
    foreach ($data_combined as $key => $value) {
        if(isset($data_combined[$key][$param])){
            $bool = ($data_combined[$key][$param]['done'] === 1 ? 0 : 1);
            $data_combined[$key][$param]['done'] = ($data_combined[$key][$param]['done'] === 1 ? 0 : 1);  
        } 
    }
} 
// mark entry as important
if (isset($_POST['imp'])){
    $param = $_POST['imp'];
    foreach ($data_combined as $key => $value) {
        if(isset($data_combined[$key][$param])){
            $bool = ($data_combined[$key][$param]['imp'] === 1 ? 0 : 1);
            $data_combined[$key][$param]['imp'] = ($data_combined[$key][$param]['imp'] === 1 ? 0 : 1);  
        } 
    }
} 

// REMOVE EMPTY KEYS
$data_combined = array_filter($data_combined);
    
// save new data to json file
$data_combined_json = json_encode($data_combined);
file_put_contents('notes.json',$data_combined_json);
// pprint($data_combined_json);


// make topics with items
$html  = '';
foreach ($data_combined as $topic => $items) {
    $html  .= "<div class='list'>\n\t\t\t<fieldset>\n";
    $html  .= "\t\t\t\t<legend>$topic</legend>\n";
    foreach ($items as $item) {
        $class=""; 
        if($item['del']==1){continue;}
        if($item['done']==1){$class .=' done';}
        if($item['imp']==1){$class .=' imp';}

        $html .= "\t\t\t\t<div class ='item'>\n";
        $html .= "\t\t\t\t\t<span class='$class' title='{$item['date']}' >{$item['name']}</span>\n";
        $html .= "\t\t\t\t\t<form style='display: none' action=''  method='post'><input type='hidden' name='del' value='{$item['id']}'><button type='submit' id='del_{$item['id']}'> </button></form>\n";
        $html .= "\t\t\t\t\t<form style='display: none' action=''  method='post'><input type='hidden' name='done' value='{$item['id']}'><button type='submit' id='done_{$item['id']}'> </button></form>\n";
        $html .= "\t\t\t\t\t<form style='display: none' action=''  method='post'><input type='hidden' name='imp' value='{$item['id']}'><button type='submit' id='imp_{$item['id']}'> </button></form>\n";
        $html .= "\t\t\t\t\t<span class='icons'>\n";
        $html .= "\t\t\t\t\t\t<label class='icon_del font_brighter' for='del_{$item['id']}'>&#10008; </label>\n";
        $html .= "\t\t\t\t\t\t<label class='icon_done font_brighter' for='done_{$item['id']}'> &#10004; </label>\n";
        $html .= "\t\t\t\t\t\t<label class='icon_imp font_brighter' for='imp_{$item['id']}'> &#10033; </label>\n";
        $html .= "\t\t\t\t\t</span>\n";
        $html .= "\t\t\t\t</div>\n";
    }
    $html .= "\t\t\t</fieldset>\n\t\t</div>\n";
} 


?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mail2Note</title>
    <style>
    @font-face{font-family:Bree;font-style:normal;font-weight:400;src:local("Bree Regular"), local(Bree-Regular), url(css/bree.woff2) format('woff2');}
    *{-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;}
    *,*::before,*::after {  box-sizing: border-box;}
    body{background-color:#1e1e1e;display:block;font:16px Lato, Helvetica, Arial, sans-serif;font-family:Lato, Sans-Serif;margin:0px;padding: 5px 20px 20px 20px;}
    .hidden{display:none;}
    .noscrollbar::-webkit-scrollbar{display:none;}
    .noscrollbar{-ms-overflow-style:none;scrollbar-width:none;}
    h1{color:#103B73;font:2.5em Bitter, serif;line-height: .5em; padding-left:20px;}
    h2{font:2.5em Bitter, serif;margin:0;}
    a{color:inherit;text-decoration:none;}
    a.button{left:6px;position:relative;}
    .button{font-size:13px;text-align:center;}
    .font_brighter:hover{filter:brightness(120%);}
    .content{margin:0;padding:0;text-align:left;position: relative;     margin-left: auto; margin-right: auto;}
    .list{display:inline-block;font-size:20px;padding:20px;vertical-align:top;width:400px;color:#4682B4;}
    .list fieldset{border-radius:3px;border-style:solid;border-width:2px;border-color:#09568d;filter:brightness(100%);margin:.5em 1em 1.3em 0;width:350px;}
    .form{margin:0;width:100%;}
    button:hover,.button:hover{background:#202020;}
    input,button,select,textarea,.button{background:#232323;border:1px solid #315B0087;border-radius:3px;color:grey;font-family:Lato, sans-serif;height:26px;padding:.2em;width:145px;min-height: 1.8rem;}
    fieldset legend{font-family:Bitter; color:#09568d;font-size:1.2em;}
    form label{display:inline;font-family:Bitter, serif;padding-left: 5px;}
    #new_entry_hide:checked ~ div.new_entry{display: inline-block;}
    .done{color:#62d196; text-decoration: line-through;}
    .imp{color:#ff8080;}
    .icons{float:right; visibility: hidden;}
    .icon_done{color: #62d196;}
    .icon_del{color: #ff5458;}
    .icon_imp{color: #ff8080;}
    .item{clear:both; padding-top:.5em;padding-bottom:.5em;}
    .item:hover span.icons{visibility: visible;}
    .new_entry_button{font-size:50%; color:#315B0087;}
    .new_entry{font-size: 20px;padding: 20px;vertical-align: top;width: 400px; }
    .new_entry fieldset{border-radius:3px;border-style:solid;border-width:2px;border-color:#315B0087; margin:.5em 1em 1.3em 0;width:350px;}
    .new_entry fieldset legend{font-family:Bitter; color:#315B0087;font-size:1.2em;}
    .new_entry input{width:137px;}
<?=$wrong_key_css?>
    .new_entry input.submit{width:31px; font-size:15px; color: #315B0087;}
    .new_entry textarea{clear:both; width:316px; height: 100px; padding:5px; font-size:14px; margin-top:5px; resize: none;}
    </style>
<?= pprint_css();?>
</head>
<body>
    <div class="content">
    <h1><a href="">Mail2Note</a><label class='new_entry_button font_brighter' for='new_entry_hide'>&nbsp;&#9998;</label></h1> 
        <?php if (isset($_GET['array'])){pprint($data_combined,'data_combined');} ?>
        <input type="checkbox" id="new_entry_hide" class="hidden" <?=$checked?>>
        <div class="new_entry hidden">
            <fieldset>
                <legend>New Entry</legend>
                    <form method="post" action="" id="new_entry">
                    <input type="password" name="key" class="key" placeholder="<?=$wrong_key?>Key">
                    <input type="text" name="topic" placeholder="Topic">
                    <input type="submit" value="&#10004;" class="submit font_brighter">
                </form>
                <textarea cols="50" class="noscrollbar" rows="10" name="items" form="new_entry" placeholder="each item in a new line"></textarea> 
            </fieldset>
        </div>

        <?= $html ?>

    </div>
</body>

 

<?
/*‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾ PRETTY_PRINT ‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾‾*/
function pprint_css() {
    echo <<<EOL
    <style>
\t#pretty_print {font-family: Consolas, monaco, monospace;font-size: 1em;background-color: #b1b1b1;border: 1px solid #949494;border-radius: 5px;width: max-content;margin: 20px;}
\t#pretty_print input[type="checkbox"] {position: absolute;left: -100vw;}
\t#pretty_print label {display: inline-block;width: 100%;font-weight: bold;margin: .2em;cursor: pointer;}
\t#pretty_print label span.linenumber {position: relative;top: 3px;right: 10px;float:right;font-weight: normal;font-size: 80%; color:black;}
\t#pretty_print pre {background: lightgray;margin: 0px;padding: 5px;overflow-y: scroll;max-height: 400px;padding-right: 50px;}
\t#pretty_print pre::-webkit-scrollbar {display: none;}
\t#pretty_print pre{-ms-overflow-style: none;  scrollbar-width: none; }
\t#pretty_print pre span {line-height: 1.5em;}
\t#pretty_print pre span.null {color: black;}
\t#pretty_print pre span.boolean {color: brown;}
\t#pretty_print pre span.double {color: darkgreen;}
\t#pretty_print pre span.integer {color: green;}
\t#pretty_print pre span.string {color: darkblue;}
\t#pretty_print pre span.array {color: black;}
\t#pretty_print pre span.object {color: black;}
\t#pretty_print pre span.type {color: grey;}
\t#pretty_print pre span.public {color: darkgreen;}
\t#pretty_print pre span.protected {color: red;}
\t#pretty_print pre span.private {color: darkorange;}
\t</style>\n
EOL;    
}

function pprint($arr, $name=1, $printable = 0, $type      = 0, $hide      = 0) {
    $bt        = debug_backtrace();
    $caller    = array_shift($bt);
    $id        = random_int(0, 999);
    $name = ($name===1)?get_var_name($arr):$name;
    echo "\n<!-- PRETTY_PRINT -->\n";
    // var_dump($caller);
    echo "<div id='pretty_print'>\n\t";
    echo "<style>#hide_$id:checked ~ pre{display: none;}</style>\n\t";
    echo "<label for='hide_$id'>$" . $name . " <span class='linenumber'>&nbsp; " . basename($caller['file']) . ":" . $caller['line'] . "</span></label>\n\t";
    echo "<input type='checkbox' id='hide_$id' class='hidden' " . (($hide) ? ' checked' : '') . " >\n\t";
    echo "<pre>\n";
    pprint_array($arr, "", $printable, $type);
    echo ($printable) ? ";" : '';
    echo "\t</pre>\n";
    echo "</div>\n";
    echo "<!-- PRETTY_PRINT -->\n\n";
}
function pprint_array($arr, $p, $printable, $type) {
    if ($printable == 1) {
        $arround = array(
            'array_1'        => 'array(',
            'array_2'        => ')',
            'key_1'          => '"',
            'key_2'          => '"',
            'value_1'        => '"',
            'value_2'        => '"',
            'type_1'         => '[',
            'type_2'         => ']',
            'sep'            => ','
        );
    }
    else {
        $arround = array(
            'array_1'         => '',
            'array_2'         => '',
            'key_1'          => '',
            'key_2'          => '',
            'value_1'        => '',
            'value_2'        => '',
            'type_1'         => '',
            'type_2'         => '',
            'sep'            => ''
        );
    }
    $t = gettype($arr);
    switch ($t) {
        case "NULL":
            echo '<span class="null"><b>NULL</b></span>' . $arround['sep'];
        break;
        case "boolean":
            echo '<span class="boolean">' . ($arr == 0 ? "false" : "true") . '</span>' . $arround['sep'] . (($type) ? ' <span class="type">boolean</span>' : '');
        break;
        case "double":
            echo '<span class="double">' . $arr . '</span>' . $arround['sep'] . (($type) ? ' <span class="type">double</span>' : '');
        break;
        case "integer":
            echo '<span class="integer">' . $arr . '</span>' . $arround['sep'] . (($type) ? ' <span class="type">integer</span>' : '');
        break;
        case "string":
            echo $arround['value_1'] . '<span class="string">' . $arr . '</span>' . $arround['value_2'] . $arround['sep'] . (($type) ? ' <span class="type">string(' . strlen($arr) . ')</span>' : '');
        break;
        case "array":
            echo $arround['array_1'] . (($type) ? ' <span class="type">(' . count($arr) . ')</span>' : '') . "\r\n";
            foreach ($arr as $k => $v) {
                if (gettype($k) == "string") {
                    echo $p . "\t" . $arround['key_1'] . $k . $arround['key_2'] . ' => ';
                }
                else {
                    echo $p . "\t" . "" . $k . " => ";
                }
                pprint_array($v, $p . "\t", $printable, $type);
                echo "\r\n";
            } // foreach $arr
            echo $p . $arround['array_2'] . $arround['sep'];
        break;
        case "object":
            $class = get_class($arr);
            $super = get_parent_class($arr);
            echo "<span class='object'>Object</span>(" . $class . ($super != false ? " exdends " . $super : "") . ")";
            echo (($printable) ? "{" : '') . "\r\n";
            $o      = (array)$arr;
            foreach ($o as $k      => $v) {
                $o_type = "";
                $name   = "";
                if (substr($k, 1, 1) == "*") {
                    $o_type = "protected";
                    $name   = substr($k, 2);
                }
                else if (substr($k, 1, strlen($class)) == $class) {
                    $o_type = "private";
                    $name   = substr($k, strlen($class) + 1);
                }
                else if ($super != false && substr($k, 1, strlen($super)) == $super) {
                    $o_type = $super . " private";
                    $name   = substr($k, strlen($super) + 1);
                }
                else {
                    $o_type = "public";
                    $name   = $k;
                }
                if ($printable) {
                    echo $p . "\t" . $arround['type_1'] . "<span class='$o_type'>" . $o_type . ": " . $name . "</span>" . $arround['type_2'] . " => ";
                }
                else {
                    echo $p . "\t" . "<span class='$o_type'>" . $name . "</span> => ";
                }
                pprint_array($v, $p . "\t", $printable, $type);
                echo "\r\n";
            }
            echo $p . ($printable) ? "}" : '';
            break;
        default:
            break;
        } // switch
        
    } // function
    // get name of $var as string
    function get_var_name($var) {
        foreach ($GLOBALS as $var_name => $value) {
            if ($value === $var) {
                return $var_name;
            }
        }
        return 'array';
    }
/*_________________________________________________ PRETTY_PRINT _________________________________________________*/
