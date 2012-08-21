<?php
Configuration::$instance = new Configuration();
$config = &Configuration::$instance;

/*
 * 全体に関する設定
 */

// タイトル
$config->title = "Megalopolis";

// 管理者パスワード (ハッシュ)
// テストモードの「管理者パスワード用ハッシュ算出」を使用して得た任意のパスワードのハッシュを指定します。
// 既定のパスワードは passwd になっています。必ず変更することを推奨します。
$config->adminHash = "109823e01afb58706d9c0e40062a505c00d814235a9900ae2f16c3e5f90d07d8895196ac33a87ac6a43ffd56ff6338378b626dd01df4e53102db7";
// 推奨されませんが、ハッシュではなく以下のように平文でパスワードを設定することも可能です。
// $config->adminHash = Util::hash("passwd");

// 管理用ツールが使用可能か (true/false)
// 詳細については、readme.txt を参照してください。
// 公開された環境で使用する場合、false にすることを推奨します
$config->utilsEnabled = true;

// .htaccess の RewriteRule 自動設定をするか (true/false)
// .htaccess が存在し、パラメータなしのページにアクセスしたときに、
// RewriteRule の書き換え先パスを自動的に現在のドキュメントルートに合うように書き換えます。
// この機能を使用するには .htaccess が書き換え可能である必要があります。
// 設置後一度設定の反映を確認したら false にすることを推奨します
$config->htaccessAutoConfig = true;

// URL の形式 (特定値)
// - Configuration::LINK_AUTO		自動検出
// - Configuration::LINK_REWRITE	直接
// - Configuration::LINK_PATH		PATH_INFO 形式
// - Configuration::LINK_QUERY		クエリ形式
$config->linkType = Configuration::LINK_AUTO;

// BBQ (http://bbq.uso800.net/) による公開プロキシ規制を使用するか (特定値)
// - Configuration::BBQ_NONE		使用しない
// - Configuration::BBQ_READ		閲覧に対して使用する
// - Configuration::BBQ_WRITE		送信に対して使用する
// - Configuration::BBQ_READWRITE	両方に対して使用する
$config->useBBQ = Configuration::BBQ_NONE;

// 閲覧に対するホスト規制 (文字列一覧)
// この一覧にあるものと IP アドレスまたはリモートホストが一致すると閲覧不可になります。
// ワイルドカード (*, ?, [] など) も使用可能です
$config->denyRead = array
(
	// 例:
	// "192.168.*",
	// "*.example.com",
);

// 送信に対するホスト規制 (文字列一覧)
// この一覧にあるものと IP アドレスまたはリモートホストが一致すると送信不可になります。
// ワイルドカード (*, ?, [] など) も使用可能です
$config->denyWrite = array
(
	// 例:
	// "192.168.*",
	// "*.example.com",
);

// 本文も検索の対象として登録するか (true/false)
// 投稿時に本文も検索用作品を作成するかどうかを指定します。
// 投稿に時間がかかったり、ログ変換に失敗したりするようであれば false にすることを推奨します。
// すでに投稿されている作品に対しては反映されません
$config->registerBodyToSearchIndex = true;

/*
 * 評価に関する設定
 */

// コメント可能かどうか (true/false)
$config->useComments = true;

// コメントなし評価の点数設定 (整数一覧)
// 何もない場合、コメントなし評価は不可になります
$config->pointMap = array
(
	50,
	40,
	30,
	20,
	10
);

// コメントつき評価の点数設定 (整数一覧)
// 何もない場合、コメント時の評価は不可になります
$config->commentPointMap = array
(
	100,
	90,
	80,
	70,
	60,
	50,
	40,
	30,
	20,
	10
);

/*
 * 投稿に関する設定
 */

// 管理者のみ投稿可能にする (true/false)
$config->adminOnly = false;

// 既定の名前 (文字列)
$config->defaultName = "名前が無い程度の能力";

// 名前を必須にするか (true/false)
// false の場合、名前が空欄の場合代わりに既定の名前が使用されます
$config->requireName = array
(
	Configuration::ON_ENTRY => false,		// 作品において必須にするか
	Configuration::ON_COMMENT => false,		// コメントにおいて必須にするか
);

// 編集または削除キーの入力を必須にするか (true/false)
$config->requirePassword = array
(
	Configuration::ON_ENTRY => false,		// 作品において必須にするか
	Configuration::ON_COMMENT => false,		// コメントにおいて必須にするか
);

// 投稿キー (文字列)
// 作品またはコメントの投稿時に入力する必要のある共通のパスワードを指定します。
// null または空欄の場合は使用されません
$config->postPassword = "";

// 分類タグの最大数 (整数)
// 0 にすると使用不可になります
$config->maxTags = 10;

// 文字色を指定できるかどうか (true/false)
$config->foregroundEnabled = true;

// 文字色パレットの一覧 (HTML 色一覧)
$config->foregroundMap = array
(
	"#0c0c0c",
	"#05057f",
	"#057f05",
	"#7f7f05",
	"#7f0505",
);

// 背景色を指定できるかどうか (true/false)
$config->backgroundEnabled = true;

// 背景色パレットの一覧 (HTML 色一覧)
$config->backgroundMap = array
(
	"#f5f5f5",
	"#f5f5ff",
	"#f5fff5",
	"#fffff5",
	"#fff5f5",
);

// 背景画像を指定できるかどうか (true/false)
$config->backgroundImageEnabled = true;

// 背景画像パレットの一覧 (画像ファイル名一覧)
$config->backgroundImageMap = array
(
	// 例:
	// "style/addIcon.png",
);

// 作品集分割件数 (整数)
// 現在の作品集にこの数の作品があったら、新規投稿される作品は新しい作品集に所属します。
$config->subjectSplitting = 100;

// 作品の禁止ワード (文字列一覧)
$config->disallowedWordsForEntry = array
(
	// 例:
	// "NG",
);

// コメントの禁止ワード (文字列一覧)
$config->disallowedWordsForComment = array
(
	// 例:
	// "NG",
);

// 禁止ワードにより投稿がエラーになったとき、原因となったワードを表示するか (true/false)
$config->showDisallowedWords = false;

// 本文の最小サイズ (bytes)
// 0 の場合無制限になります
$config->minBodySize = 0;

// 本文の最大サイズ (bytes)
// 0 の場合無制限になります
$config->maxBodySize = 0;

// 概要を使用可能にするか (true/false)
$config->useSummary = true;

// 概要の最大行数 (整数)
// 0 の場合無制限になります
$config->maxSummaryLines = 0;

// 概要の最大サイズ (bytes)
// 0 の場合無制限になります
$config->maxSummarySize = 0;

/*
 * 表示に関する設定
 */

// 見た目の設定 (特定値)
// - null							既定の緑ベースのもの
// - "alt"							Megalith ライクな赤ベースのもの
$config->skin = null;

// 作品集表示順 (特定値)
// - Configuration::ORDER_ASCEND	昇順
// - Configuration::ORDER_DESCEND	降順
// ただし、最新作品集は常に先頭に表示
$config->subjectOrder = Configuration::ORDER_DESCEND;

// Rate の計算式 (特定値)
// - Configuration::RATE_FIVE		5 を基準にした率を表します。((POINT + 25) / ((評価数 + 1) * 50)) * 10
// - Configuration::RATE_AVERAGE	評価ごとの平均点を表します。
$config->rateType = Configuration::RATE_FIVE;

// 検索時に一ページに表示する件数 (整数)
$config->searchPaging = 30;

// タグ一覧の一ページに表示する件数 (整数)
$config->tagListing = 100;

// head タグ内の追加のタグ (HTML)
$config->head = trim
('
');

// 作品一覧表示時に作品集一覧の上に表示されるお知らせ (HTML)
$config->notes = trim
('
	Hello!
');

// フッターにバージョン情報を表示するか (true/false)
$config->showFooterVersion = true;

// フッターに表示する追加情報 (HTML 一覧)
$config->footers = array
(
	'<a href="' . Visualizer::actionHref("tag") . '">タグ一覧</a>',
	'<a href="' . Visualizer::actionHref("author") . '">作者一覧</a>',
	'<a href="' . Visualizer::actionHref("config.html") . '">設定情報</a>',
);

// 既定の一覧表示形式 (特定値)
// - Configuration::LIST_DOUBLE		二列で表示します。
// - Configuration::LIST_SINGLE		一列で表示します。
$config->listType = Configuration::LIST_DOUBLE;

// New や Up と表示される期間 (日)
// 0 の場合無効になります
$config->updatePeriod = 3;

// 本文枠内に作品名および名前を表示するか (true/false)
$config->showHeaderInsideBorder = false;

// コメントおよび簡易評価両方が有効な場合の既定の評価方法 (特定値)
// - Configuration::EVAL_POINT		簡易評価タブを既定にします。
// - Configuration::EVAL_COMMENT	コメントタブを既定にします。
$config->defaultEvaluator = Configuration::EVAL_POINT;

// 作品名を表示するか (true/false)
$config->showTitle = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか。false にすると作品自体も閲覧できなくなります
);

// 名前を表示するか (true/false)
$config->showName = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
	Configuration::ON_COMMENT => true,		// 各コメントに表示するか
);

// 分類タグを表示するか (true/false)
$config->showTags = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// 概要を表示するか (true/false)
$config->showSummary = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// 閲覧数を表示するか (true/false)
$config->showReadCount = array
(
	Configuration::ON_SUBJECT => false,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// 得点を表示するか (true/false)
$config->showPoint = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
	Configuration::ON_COMMENT => true,		// 各コメントに表示するか
);

// Rate を表示するか (true/false)
$config->showRate = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// コメントを表示するか (true/false)
$config->showComment = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// サイズを表示するか (true/false)
$config->showSize = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// ページ数を表示するか (true/false)
$config->showPages = array
(
	Configuration::ON_SUBJECT => true,		// 作品一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// Tweet Button を表示するか (true/false)
$config->showTweetButton = true;

/*
 * ログの変換に関する設定
 */

// ログ変換時の分割数 (整数)
// Megalith 形式のログを変換する時の一呼び出し毎の変換作品数を指定します。
// この数を下げることにより PHP の最大実行時間により強制終了されることなく変換処理が続行できます。
// その代わりに、変換に要する時間はより長くなります。
// 変換中に Maximum execution time of ～ seconds exceeded などとエラーが出て中止される場合は適当に下げてみてください。
// また、本文の検索作品を作成することで実行時間が長くなり失敗することもあるので、そちらの設定 (registerBodyToSearchIndex) もご確認ください
$config->convertDivision = 100;

// 必要に応じて Megalith のログを読み込むかどうか (true/false)
// Megalith/ 以下にある Megalith 形式のログを使用するかどうかを指定します
$config->convertOnDemand = false;

// 設定終了
unset($config);
?>