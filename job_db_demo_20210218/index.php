<?php
/*
 * CMS管理システム
 * Copyright (c) 2010-2019 by Crytus All rights reserved.
 *
 * 【注意】このファイルを誤って修正すると出力ができなくなります。
 * 修正する前に必ずファイルのコピーを保存しておいてください。
 *
 * 2013/07/18 get_newinfo処理修正
 * 2016/08/04 新CMS対応
 * 2016/09/02 登録・更新日時出力対応
 * 2016/09/03 問い合わせ機能修正
 * 2016/09/07 詳細画面HTMLファイル指定
 * 2017/04/10 同日のお知らせを表示可能に
 * 2017/09/04 複数項目の検索処理の問題を修正
 * 2019/09/05 管理画面の入力機能追加対応
 */
ini_set("short_open_tag", "0");
ini_set("magic_quotes_gpc", "0");
ini_set("mbstring.encoding_translation", "0");

define("INFO_PROPATY", 4);		// プロパティ

// エラー出力の指定
error_reporting (E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);

// データベースの漢字コード
define("DB_ENCODING", "UTF-8");

// メールの漢字コード(UTF-8かJIS)
define("MAIL_ENCODING", "JIS");
//define("MAIL_ENCODING", "UTF8");

// セッションの開始
session_start();

include_once("setup.php");

if (defined("SCRIPT_ENCODING")) {
	$script_encoding = SCRIPT_ENCODING;
} else {
	$script_encoding = "UTF-8";
}
mb_regex_encoding($script_encoding);

// 1ページの件数
if (!defined("PAGE_LIMIT")) {
	define("PAGE_LIMIT", 10);	// 10件
}

// 動作環境チェック
if (!function_exists("mb_convert_encoding")) {
	error_exit("日本語変換処理が使えません。ご確認をお願い致します。");
}
if (!class_exists("PDO")) {
	error_exit("簡易データベース(SQLite3)が使えません。ご確認をお願い致します。");
} else {
	$ary = PDO::getAvailableDrivers();
	if (!in_array("sqlite", $ary)) {
		error_exit("簡易データベース(SQLite3)が使えません。ご確認をお願い致します。");
	}
}
if (!function_exists("ImageCreateFromString")) {
	error_exit("画像処理が組み込まれていません。ご確認をお願い致します。");
}
if (substr(phpversion(), 0, 1) == "4") {
	error_exit("このシステムはPHP5 が必要です。ご確認をお願い致します。");
}
// 異常終了
function error_exit($msg)
{
	echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
	echo $msg;
	echo "</body></html>";
	exit;
}
//-------------------------------------------------------------
//define("MAX_REC_SIZE", 4000);	// 1レコードの最大バイト数

// magic_quotes_gpc対策
if (get_magic_quotes_gpc()) {
	$_REQUEST = safeStripSlashes($_REQUEST);
}

$dbh = new PDO($DB_URI["db"], $DB_URI["user"], $DB_URI["password"]);

function exec_file($file)
{
	global $setup;
	global $DB_URI;
	global $script_encoding;
	if (file_exists("_files/" . $file)) {
		include_once("_files/" . $file);
	} else {
		$php = load_blob($file);
		eval("?>" . $php);
	}
}
// 大きなデータを取り出す
function load_blob($key)
{
	global $dbh;

	$sql = "select seq from file_list where name='{$key}'";
	$ret = $dbh->query($sql);
	$val = $ret->fetchAll();

	if ($val) {
		$val = $val[0];
		$id = $val["seq"];
		$file = load_db_file($id);
		return $file;
	}
	return false;
}
function load_db_file($id)
{
	global $dbh;

	$data = "";
	$sql = "select contents from file_contents where file_num={$id} order by seq";
	//$result = sqlite_query($dbh, $sql);
	$stmt = $dbh->query($sql);
	while ($ret = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$data .= $ret["contents"];
	}
	return $data;
}
function safeStripSlashes($var) {
	if (is_array($var)) {
		return array_map('safeStripSlashes', $var);
	} else {
		if (get_magic_quotes_gpc()) {
			$var = stripslashes($var);
		}
		return $var;
	}
}

$act = $_REQUEST["act"];
if (!$act) {
	$act = "index";
}
/*
// 管理画面のログインを無効にする
if (($act == "setup")||($act == "login")) {
	if ($_SERVER["SERVER_NAME"] != "") {
		header("location: ./");
		exit;
	}
}
*/
// バイナリー処理のファイル
$binary = array(
	"png",
	"jpg",
	"gif",
);
if ($act == "file") {
	$file = $_REQUEST["f"];
	if ($file) {
		if (file_exists($file)) {
			$contents = file_get_contents($file);
		} else if (file_exists("_files/" . $file)) {
			$contents = file_get_contents("_files/" . $file);
		} else {
			$contents = load_blob($file);
			$ary = explode(".", $file);
			if (count($ary) > 1) {	// 拡張子あり？
				if (in_array($ary[count($ary)-1], $binary)) {
					$contents = base64_decode($contents);
				}
			}
		}
	}
	header('Content-Type: ' . get_mime_types($file));
	echo $contents;
	exit;
}
// --------------------------------
exec_file("config.php");
exec_file("pdo_db.inc");
exec_file("dbaccess.inc");
exec_file("cms.inc");
exec_file("info.inc");
exec_file("info_item.inc");
exec_file("image.inc");
exec_file("item.inc");
exec_file("access.inc");
exec_file("htmltemplate.inc");
exec_file("cms_core.php");
// --------------------------------
// アクセスカウント
if ((!$_SESSION["LOGIN"])&&(!$_SESSION["USER"])) {
	$cond["yy"] = date('Y');
	$cond["mm"] = date('m');
	$cond["dd"] = date('d');
	$ret = Access::findData($cond);
	if ($ret) {
		$cond["user_count"] = $ret[0]["user_count"] + 1;
		Access::updateData($ret[0]["seq"], $cond);
	} else {
		$cond["user_count"] = "1";
		Access::addData($cond);
	}
	$_SESSION["USER"] = time();
	$_SESSION["INFO"] = array();
}
// --------------------------------
if (!$_SESSION["TOTAL"]) {
	$sql = "select count(*) as count,kind from item where open=1 group by kind";
	$inst = DBConnection::getConnection($DB_URI);
	$ret = $inst->search_sql($sql);
	$count = array();
	$total = 0;
	if ($ret["data"]) {
		foreach ($ret["data"] as $val) {
			$count[$val["kind"] - 100] = $val["count"];
			$total += $val["count"];
		}
		$count["total"] = $total;
	}
	$_SESSION["TOTAL"] = $count;
}
$data["total"] = $_SESSION["TOTAL"];
// --------------------------------
if ($act == "list") {	// 一覧
	$page = $_REQUEST["page"];
	if (!$page) {
		$page = 1;
	}
	$ord = $_REQUEST["ord"];
	if (!$ord) {
		$ord = "";
	}
	$kind = $_REQUEST["kind"];
	if (!$kind) {
	//	$kind = "1";
	}
	$top = $_REQUEST["top"];
	if (!$top) {
		$top = "0";
	}
	$limit = $_REQUEST["limit"];
	if (!$limit) {
		$limit = PAGE_LIMIT;
	}
	$html = $_REQUEST["html"];
	//
	$form["ord"] = $ord;
	$form["kind"] = $kind;
	//
	$propaty = get_item_propaty(false, $kind);
	// 項目検索
	$search = array();
	for ($i = 1; $i <= 20; $i++) {
		if (isset($_REQUEST["info" . $i])) {
			$v = trim($_REQUEST["info" . $i]);
			if ($v) {
				$search["info" . $i] = $v;
				$form["info" . $i] = $v;
				$form["info" . $i . "_" . $v] = "selected";
			}
		}
		if ($_REQUEST["info" . $i . "HL"]) {
			$v = trim($_REQUEST["info" . $i . "HL"]);
			$ary = explode(" ", $v);
			if (count($ary > 1)) {
				$search["info" . $i . "_L"] = $ary[0];
				$search["info" . $i . "_H"] = $ary[1];
			} else {
				$search["info" . $i . "_L"] = $ary[0];
				$search["info" . $i . "_H"] = $ary[0];
			}
			$form["info" . $i . "HL_" . $v] = "selected";
		}
		if (isset($_REQUEST["info" . $i . "L"]) || isset($_REQUEST["info" . $i . "H"])) {
			$v = trim($_REQUEST["info" . $i . "L"]);
			if ($v) {
				$search["info" . $i . "_L"] = $v;
				$form["info" . $i . "L"] = $v;
			}
			$v = trim($_REQUEST["info" . $i . "H"]);
			if ($v) {
				$search["info" . $i . "_H"] = $v;
				$form["info" . $i . "H"] = $v;
			}
		}
		if (isset($_REQUEST["info" . $i . "R"])) {
			$v = trim($_REQUEST["info" . $i . "R"]);
			if ($v) {
				$search["info" . $i . "_S"] = $v;
				$form["info" . $i . "R" . $v] = "checked";
			}
		}
		if (isset($_REQUEST["info" . $i . "S"])) {
			$v = trim($_REQUEST["info" . $i . "S"]);
			if ($v) {
				$search["info" . $i . "_S"] = $v;
				$form["info" . $i . "S" . $v] = "selected";
			}
		}
		if (isset($_REQUEST["info" . $i . "C"])) {
			foreach ($_REQUEST["info" . $i . "C"] as $v) {
				if ($v) {
					$search["info" . $i . "_C"][] = $v;
					$form["info" . $i . "C" . $v] = "checked";
				}
			}
		}
		if (isset($_REQUEST["info" . $i . "X"])) {
			foreach ($_REQUEST["info" . $i . "X"] as $v) {
				if ($v) {
					$search["info" . $i . "_X"][] = $v;
					$form["info" . $i . "X" . $v] = "checked";
				}
			}
		}
	}
	// こだわり検索
	$special = array();
	$special_item = array();
	for ($i = 1; $i <= 20; $i++) {
		$v = 0;
		if (isset($_REQUEST["special" . $i])) {
			$v = trim($_REQUEST["special" . $i]);
		}
		if ($propaty["special" . $i]) {
			$form["special" . $i] = $v;
			unset($item);
			$item["no"] = $i;
			$item["title"] = $propaty["special" . $i];
			$item["icon"] = $setup["icons"][$kind][$i];
			if ($v) {
				$special["special" . $i] = $v;
				$item["value"] = $v;
			}
			$special_item[] = $item;
		}
	}
	$form["special"] = $special_item;
	//
	$data["form"] = $form;
	//
	$data["title"] = $setup["bukken"][$kind];
	list($list, $pager, $counter) = item_list($kind, $ord, $page, $limit, $top, $search, $special);		// こだわりAND検索
//	list($list, $pager, $counter) = item_list($kind, $ord, $page, $limit, $top, $search, $special, 'or');		// こだわりOR検索
	$data["list"] = $list;
	$data["pager"] = $pager;
	$data["counter"] = $counter;
	$data["order"] = $ord;
	$data["ord" . $ord] = "1";
	$data["kind"] = $kind;
	$data["limit"] = $limit;
	$data["html"] = $html;
	$data["top"] = $top;
	//
	if (!$html) {
		$html = $propaty["list_html"];
	}
	$data["osusume"] = item_list($kind, 0, 0, 10, 2); // ●●●種類ごとのおすすめ
	htmltemplate::t_include($html, $data);
	exit;
}
// 詳細
if (($act == "detail")||($act == "bukken")) {
	$html = $_REQUEST["html"];
	$id = $_REQUEST["id"];
	if ($id) {
		// 詳細
		$item = get_item($id);
		if ($item["open"] != 1) {		// 非公開
			if (!$_SESSION["LOGIN"]) {	// 管理者は閲覧可
				header("location: ./");
				exit;
			}
		}
		if ($item["pdf"]) {		// PDF ファイル
			$item["pdf_file"] = Image::getData($item["pdf"]);
		}
		for ($i = 1; $i <= 10; $i++) {
			if ($item["file" . $i]) {		// ファイル1
				unset($v);
				$item["file" . $i . "_file"] = Image::getData($item["file" . $i]);
				$filetype = get_filetype($item["file" . $i . "_file"]);
				$item["file" . $i][$filetype] = $filetype;
				// 種類別
				$v["no"] = $i;
				$v["file"] = $item["file" . $i];
				$v["file_file"] = $item["file" . $i . "_file"];
				$v["file"][$filetype] = $filetype;
				$data[$filetype][] = $v;
			}
		}
		if ($data["image"]) {
			$data["image_default"] = $data["image"][0];
		}
		if ($data["audio"]) {
			$data["audio_default"] = $data["audio"][0];
		}
		if ($data["video"]) {
			$data["video_default"] = $data["video"][0];
		}
		if ($data["panorama"]) {
			$data["panorama_default"] = $data["panorama"][0];
		}
		if ($data["other"]) {
			$data["other_default"] = $data["other"][0];
		}
		$data["item"] = $item;
		// アクセス数
		if ((!$_SESSION["LOGIN"])&&(!$_SESSION["INFO"][$id])) {
			$_SESSION["INFO"][$id] = $id;
			unset($rec);
			if ($item["view_count"]) {
				$rec["view_count"] = $item["view_count"] + 1;
			} else {
				$rec["view_count"] = "1";
			}
			Item::updateData($id, $rec);
		}
		//
		$data["osusume"] = item_list($item["kind"], 0, 0, 10, 2);	// ●●●種類ごとのおすすめ
		if (!$html) {
			$html = $item["propaty"]["item_html"];
		}
		htmltemplate::t_include($html, $data);
		exit;
	}
}
if (($act == "toiawase")||($act == "toiawase_reinput")) {
	$id = $_REQUEST["id"];
	$item = get_item($id);
	if ($id && $item) {
		$data["item"] = $item;
		$mailitem = explode(",", $mail_item);
	} else {
		$mailitem = explode(",", $mail_item2);
	}
	if (!$mailitem) {
		echo "設定ファイルを確認してください";
		exit;
	}
	$data["mailitem"] = array();
	foreach ($mailitem as $val) {
		$data["mailitem"][$val] = "1";
	}
	//
	$mode = $_REQUEST["mode"];
	if ($mode == "form") {
		foreach ($mailitem as $val) {
			$form[$val] = $_REQUEST[$val];
		}
		$data["form"] = $form;
		$msg = array();
		foreach ($mailitem as $val) {
			if ($error[$val] &&(!$form[$val])) {
				$msg[] = $error[$val];
			}
		}
		if (!$msg) {
			$data["mode"] = "confirm";
			$data["confirm"] = "1";
			$_SESSION["form"] = $form;
		} else {
			$data["message"] = join("<br/>", $msg);
			$data["mode"] = "form";
		}
	} else if ($mode == "confirm") {
		$form = $_SESSION["form"];
		if ($item["info_id"]) {
			$body = $mail_body;
			$body = str_replace("{info_id}", $item["info_id"], $body);
			$body = str_replace("{title}", $item["title"], $body);
		} else {
			$body = $mail_body2;
		}
		foreach ($mailitem as $val) {
			$body = str_replace("{" . $val . "}", $form[$val], $body);
		}
		// メール送信
		$tmp = $pre_admin . "\n" . $body . "\n\n" . $post_admin;
		if ($admin_mail) {
			sendmail2($from_mail, $admin_mail, $subject_admin, $tmp, null, $from_name);
		}
		if ($form["email"]) {
			$tmp = $form["name"] . "{$mail_sama}\n\n{$pre_user}\n{$body}\n\n{$post_user}";
			sendmail2($from_mail, $form["email"], $subject, $tmp, null, $from_name);
		}
	} else {
		$data["mode"] = "form";
	}
	if ($act == "toiawase_reinput") {
		$data["form"] = $_SESSION["form"];
		$data["mode"] = "form";
	}
	$data["osusume"] = item_list($item["kind"], 0, 0, 10, 2);	// ●●●種類ごとのおすすめ
	//
	htmltemplate::t_include("toiawase.html", $data);
	exit;
}
// --------------------------------
// トップページ
$data["osusume"] = item_list(0, 0, 0, 12, 2);	// おすすめ
//
$data["list0"] = item_list(0, 0, 0, 12, 1);	// 全種類
$data["list1"] = item_list(1, 0, 0, 12, 1);	// 種類1
$data["list2"] = item_list(2, 0, 0, 12, 1);	// 種類2
$data["list3"] = item_list(3, 0, 0, 12, 1);	// 種類3
$data["list4"] = item_list(4, 0, 0, 12, 1);	// 種類4
//
$data["newinfo"] = get_newinfo();
//
htmltemplate::t_include("index_.html", $data);
exit;
// --------------------------------
// お知らせ
function get_newinfo($max=10)
{
	global $DB_URI;

	$sql = "select * from info where open=1 and kind=" . INFO_RSS;
	$inst = DBConnection::getConnection($DB_URI);
	$ret = $inst->search_sql($sql);
	$list = array();
	if ($ret["count"]) {
		foreach ($ret["data"] as $val) {
			$item = get_setup(INFO_RSS, $val["info_id"]);
			if ($item["open"]) {
				$item["reg_date"] = $val["reg_date"];
				if (newflag(-1, $item["rss_date"])) {
					$item["new_flag"] = "1";
				}
				$t = strtotime($item["rss_date"]);
				while (1) {
					if ($list[$t]) {
						$t++;
					} else {
						$list[$t] = $item;
						break;
					}
				}
			}
		}
	}
	// 並べ替え
	$rss = array();
	if ($list) {
		$count = 0;
		krsort($list);
		foreach ($list as $val) {
			$val["rss_date"] = substr($val["rss_date"], 0, 4) . "/" . substr($val["rss_date"], 5, 2) . "/" . substr($val["rss_date"], 8, 2);
			$rss[] = $val;
			$count++;
			if ($count >= $max) {
				break;
			}
		}
	}
	return $rss;
}
// --------------------------------
// プロパティ
function get_item_propaty($inst, $kind)
{
	global $DB_URI;

	if ($_SESSION["PROPATY" . $kind]) {
//		return $_SESSION["PROPATY" . $kind];
	}
	$propaty = array();
	if (!$inst) {
		$inst = DBConnection::getConnection($DB_URI);
	}
	$sql = "select kind,value from info_item where info_id in (select info.info_id from info left join info_item on info.info_id=info_item.info_id where info.kind=" . INFO_PROPATY . " and info_item.kind='kind' and info_item.value='{$kind}')";
	$ret = $inst->search_sql($sql);
	if ($ret["count"]) {
		foreach ($ret["data"] as $val) {
			$propaty[$val["kind"]] = $val["value"];
		}
	}
	if (!$propaty["list_html"]) {
		$propaty["list_html"] = "list.html";
	}
	if (!$propaty["item_html"]) {
		$propaty["item_html"] = "item.html";
	}
	$_SESSION["PROPATY" . $kind] = $propaty;
	return $propaty;
}
// --------------------------------
function item_list($kind=0, $ord=0, $page=0, $limit=0, $top=0, $search=array(), $special=array(), $andor='and')
{
	global $DB_URI;

	$inst = DBConnection::getConnection($DB_URI);
	$join = "";
	$where = "";
	if ($kind) {
		$k = INFO_ITEM + intval($kind);
		$where = " and kind={$k}";
		// プロパティ
		$propaty = get_item_propaty($inst, $kind);
	} else {
		// プロパティ
	//	$propaty = get_item_propaty($inst, 1);
	}
	// トップページ用一覧
	if ($top) {
		if ($top == 1) {	// new
			$where .= " and new=1";
		}
		if ($top == 2) {	// オススメ
			$where .= " and recommend=1";
		}
		if (intval($top) > 10) {	// オススメ
			$where .= " and recommend={$v}";
		}
	}
	// 条件検索
	if ($search) {
		foreach ($search as $key => $val) {
			$ary = explode("_", $key);
			if ($ary[1] == "S") {
				$val = mb_convert_encoding($val, DB_ENCODING, SCRIPT_ENCODING);
				$where .= " and {$ary[0]} = '{$val}'";
			} else if ($ary[1] == "C") {
				unset($cond);
				unset($c);
				foreach ($val as $v) {
					if ($v) {
						$c[] = "{$ary[0]} = '{$v}'";
					}
				}
				$cond = implode(" or ", $c);
				$where .= " and ({$cond})";
			} else if (($ary[1] == "H")||($ary[1] == "L")) {
				$val = mb_convert_encoding($val, DB_ENCODING, SCRIPT_ENCODING);
				if ($ary[1] == "H") {
					$where .= " and {$ary[0]}+0 <= {$val}";
				} else {
					$where .= " and {$ary[0]}+0 >= {$val}";
				}
			} else if ($ary[1] == "X") {
				unset($cond);
				unset($c);
				foreach ($val as $v) {
					if ($v) {
						$c[] = "{$ary[0]} like '%{$v}%'";
					}
				}
				$cond = implode(" or ", $c);
				$where .= " and ({$cond})";
			} else {
				$val = mb_convert_encoding($val, DB_ENCODING, SCRIPT_ENCODING);
				$vals = explode(" ", mb_ereg_replace("　", " ", $val));
				if (count($vals) > 1) {
					unset($cond);
					unset($c);
					foreach ($vals as $v) {
						if ($v) {
							$c[] = "{$key} like '%{$v}%'";
						}
					}
					$cond = implode(" or ", $c);
					$where .= " and ($cond)";
				} else {
					$where .= " and {$key} like '%{$val}%'";
				}
			}
		}
	}
	// こだわり条件検索
	if ($special) {
		$v = 0;
		foreach ($special as $key => $val) {
			$v += 1 << $val;
		}
		if ($andor == 'and') {
			$where .= " and ((special & $v)=$v)";
		} else {
			$where .= " and (special & $v)";
		}
	}
	//
	$sql = " from item where open=1 {$where}";
	if ($page && $limit) {
		$ret = $inst->search_sql("select count(info_id) as num" . $sql);
		$count = $ret["data"][0]["num"];
		$pages = intval(($count + $limit - 1) / $limit);
		$pager = array();
		if ($pages > 1) {
		//	$pager = page_index($page, $pages);
			$pager = page_index3($page, $pages);
		}
	}
	if ($ord == 1) {
		$sql .= " order by price desc";	// 高い順
	} else if ($ord == 2) {
		$sql .= " order by price";	// 安い順
	} else if ($ord == 3) {
		$sql .= " order by reg_date desc";	// 新着
	} else if ($ord != "") {
		$sql .= " order by {$ord}";	// 項目指定
	} else {
		$sql .= " order by reg_date desc";	// 新着
	}
	if ($page && $limit) {
		$p["offset"] = ($page - 1) * $limit;
		$p["limit"] = $limit;
	} else {
		$p["offset"] = 0;
		$p["limit"] = $limit;
	}
	$ret = $inst->search_sql("select * " . $sql, false, $p);
	$list2 = array();
	if ($ret["count"] && $ret["data"]) {
		foreach ($ret["data"] as $val) {
			$list_item = array();
			$info = array();
			if (!$kind) {
				if ($_SESSION["PROPATY" . ($val["kind"] - 100)]) {
					$propaty = $_SESSION["PROPATY" . ($val["kind"] - 100)];
				} else {
					$propaty = get_item_propaty($inst, ($val["kind"] - 100));
				}
			}
			unset($list);
			foreach ($val as $key => $val2) {
				if ($key == "special") {
					$ary = explode(",", $val2);
					if ($ary) {
						foreach ($ary as $val4) {
							$key2 = "special" . $val4;
							unset($item);
							$item["title"] = $propaty[$key2];
							$item["value"] = $val4;
							if ($setup["icons"][$kind][$val4]) {
								$item["icon"] = $setup["icons"][$kind][$val4];
							}
							$list[$key2] = $item;
							$special[] = $item;
						}
					}
				} else if ($propaty[$key]) {
					unset($item);
					if ($val2) {
						$item["title"] = $propaty[$key];
						if (is_array($val2)) {
							$item["value"] = implode("・", $val2);
							$item["value_list"] = $val2;
						} else {
							$item["value"] = $val2;
						}
						$list[$key] = $item;
						if (substr($key, 0, 4) == "info") {
							$n = intval(substr($key, 4));
							if ($n <= 10) {		// 一覧項目
								$list_item[] = $item;
							}
							$info[] = $item;
						}
					}
				} else if ($key == "new") {
					if (newflag($val2, $val["reg_date"])) {
						$list["new_flag"] = "1";
					} else if (newflag(-1, $val["up_date"], 24)) {	// 新着設定は見ない
						$list["update_flag"] = "1";
					}
				} else if ($val2) {
					if (($key == "recommend") && $val2) {
						$list["recommend" . $val2] = "1";
					}
					$list[$key] = $val2;
				}
				if (substr($key, 0, 5) == "image") {
					if ($val2) {
						$img = Image::getData($val2);
						if ($img) {
							$list[$key . "_file"] = $img["save_name"];
							$list[$key . "_title"] = $img["title"];
						}
					}
				}
			}
			$ary = explode(" ", $val["reg_date"]);
			$list["regdate"] = explode("-", $ary[0]);
			$list["regtime"] = explode(":", $ary[1]);
			$ary = explode(" ", $val["up_date"]);
			$list["update"] = explode("-", $ary[0]);
			$list["uptime"] = explode(":", $ary[1]);
			//
			$list["list_item"] = set_row($list_item, 2);	// 一覧用情報
			$list["info"] = $info;	// 詳細用情報
			$list["special"] = $special;	// こだわり
			$list2[] = $list;
		}
	}
	if ($page && $limit) {
		if ($count) {
			$counter = array("total" => $count, "start" => ($page - 1) * $limit + 1, "end" => ($page - 1) * $limit + count($list2));
		}
		return array($list2, $pager, $counter);
	}
	return $list2;
}

function get_item($info_id, $propaty=null)
{
	global $DB_URI;
	global $setup;

	$item = Item::getData($info_id);
	$kind = $item["kind"] - 100;
	if (!$propaty) {
		// プロパティ
		$propaty = get_item_propaty($inst, $kind);
	}
	//
	$list = array("info_id" => $item["info_id"], "kind" => $kind, "propaty" => $propaty);
	$list_item = array();
	$info = array();
	$special = array();
	if ($item) foreach ($item as $key => $val) {
		if ($key == "kind") continue;
		if (($key == "special") && $val) {
			for ($i = 1; $i <= 20; $i++) {
				$key2 = "special" . $i;
				if ($val & (1 << $i)) {
					unset($item);
					$item["title"] = $propaty[$key2];
					$item["value"] = $i;
					if ($setup["icons"][$kind][$i]) {
						$item["icon"] = $setup["icons"][$kind][$i];
					}
					$list[$key2] = $item;
					$special[] = $item;
				}
			}
		} else if ($propaty[$key]) {
			unset($item2);
			if ($val) {
				$item2["title"] = $propaty[$key];
				if (is_array($val)) {
					$item2["value"] = implode("・", $val);
					$item2["value_list"] = $val;
				} else {
					$item2["value"] = $val;
				}
				$list[$key] = $item2;
				if (substr($key, 0, 4) == "info") {
					$n = intval(substr($key, 4));
					if ($n <= 10) {		// 一覧項目
						$list_item[] = $item2;
					}
					$info[] = $item2;
				}
			}
		} else if ($key == "new") {
			if (newflag($val, $item["reg_date"])) {
				$list["new_flag"] = "1";
			} else if (newflag(-1, $item["up_date"], 24)) {
				$list["update_flag"] = "1";
			}
		} else if ($val) {
			if (($key == "recommend") && $val) {
				$list["recommend" . $val] = "1";
			}
			$list[$key] = $val;
		}
		if (substr($key, 0, 5) == "image") {
			if ($val) {
				$img = Image::getData($val);
				if ($img) {
					$list[$key . "_file"] = $img["save_name"];
					$list[$key . "_title"] = $img["title"];
				}
			}
		}
	}
	$ary = explode(" ", $list["reg_date"]);
	$list["regdate"] = explode("-", $ary[0]);
	$list["regtime"] = explode(":", $ary[1]);
	$ary = explode(" ", $list["up_date"]);
	$list["update"] = explode("-", $ary[0]);
	$list["uptime"] = explode(":", $ary[1]);
	//
	$list["list_item"] = set_row($list_item, 2);	// 一覧用情報
	$list["info"] = $info;	// 詳細用情報
	$list["special"] = $special;	// こだわり
	return $list;
}
function set_row($ary, $cnt)
{
	$num = 0;
	$col = 0;
	$data = array();
	$row = array();
	foreach ($ary as $val) {
		$row[] = $val;
		if (++$col == $cnt) {
			$data[$num]["num"] = $num + 1;
			$data[$num++]["row"] = $row;
			$row = array();
			$col = 0;
		}
	}
	if ($row) {
		$data[$num]["num"] = $num + 1;
		$data[$num++]["row"] = $row;
	}
	return $data;
}
function page_index($cur, $pages)
{
	$page = array();
	$no = 0;
	if ($cur > 1) {
		$page['prev'] = array('no' => $cur - 1, 'name' => 'PREV', 'link' => 1);
	}
	for ($i = 1; $i <= $pages; $i++) {
		if ($i == $cur) {
			$page['list'][$no] = array('no' => $i, 'name' => $i);
		} else {
			$page['list'][$no] = array('no' => $i, 'name' => $i, 'link' => 1);
		}
		$no++;
	}
	if ($cur < ($pages)) {
		$page['next'] = array('no' => $cur + 1, 'name' => 'NEXT', 'link' => 1);
	}
	return $page;
}
// ファイルの拡張子からMIMEタイプを得る
function get_mime_types($file)
{
	$mime_types = array(
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/html',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',
		'csv' => 'text/csv',

		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',

		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',

		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',

		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',

		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',

		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	);

	$ary = explode(".", $file);
	if (count($ary) > 1) {	// 拡張子あり？
		if ($mime_types[$ary[count($ary)-1]]) {
			return $mime_types[$ary[count($ary)-1]];
		}
	}
	return "application/octet-stream";	// 不明なファイル
}
function newflag($new, $date)
{
	if (defined("NEW_DAYS") && NEW_DAYS) {
		$t = intval((time() - strtotime($date)) / 86400);
		if ($new < 0) {
			if ($t < NEW_DAYS) return 1;
		} else if (defined("NEW_OR")) {
			// OR(新着または設定日数以内の場合)
			if ($new || ($t < NEW_DAYS)) return 1;
		} else if (defined("NEW_AND")) {
			// AND(新着かつ設定日数以内の場合)
			if ($new && ($t < NEW_DAYS)) return 1;
		} else {
			// 自動判定のみ
			if ($t < NEW_DAYS) return 1;
		}
	} else {
		// 新着設定のみ
		if ($new > 0) return 1;
	}
	return 0;
}
function page_index3($cur, $pages, $maxpage=10)
{
	$cur--;
	//
	$page = array();
	$no = 0;
	if ($cur > 0) {
		$page['prev'] = array('no' => $cur, 'name' => '前へ', 'link' => 1);
	}
	$start = 0;
	$end = $pages;
	if ($pages > $maxpage) {
		if ($cur > intval($maxpage / 2)) {
			$start = $cur - intval($maxpage / 2);
		}
		$end = $start + $maxpage;
		if ($end > $pages) {
			$end = $pages;
			$start = $end - $maxpage;
		}
		if ($start > 0) {
			$page['prev_skip'] = "1";
			$page['top'] = array('no' => 1, 'name' => '1', 'link' => 1);
		}
		if ($end < $pages) {
			$page['next_skip'] = "1";
			$page['last'] = array('no' => $pages, 'name' => $pages, 'link' => 1);
		}
	}
	for ($i = $start; $i < $end; $i++) {
		if ($i == $cur) {
			$page['list'][$no] = array('no' => $i + 1, 'name' => $i + 1);
		} else {
			$page['list'][$no] = array('no' => $i + 1, 'name' => $i + 1, 'link' => 1);
		}
		$no++;
	}
	if ($cur < ($pages - 1)) {
		$page['next'] = array('no' => $cur + 2, 'name' => '次へ', 'link' => 1);
	}
	return $page;
}
function get_filetype($file)
{
	if (substr($file["filetype"], 0, 5) == "audio") {
		return "audio";
	}
	if (substr($file["filetype"], 0, 5) == "video") {
		return "video";
	}
	if (substr($file["filetype"], 0, 5) == "image") {
		return "image";
	}
	$ary = explode(".", $file["name"]);
	if (count($ary) > 1) {
		if (($ary[count($ary) - 1] == "vr") || ($ary[count($ary) - 1] == "VR")) {
			return "panorama";
		}
	}
	return "other";
}
