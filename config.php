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

// 管理用設定ツールが使用可能か (true/false)
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

// gzip 転送を許可するか (true/false)
// true の場合、ブラウザが gzip 転送に対応している場合、出力を gzip 圧縮転送し
// 転送量を削減します。
$config->useOutputCompression = true;

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
	// "192.168.*",			// IP アドレス
	// "*.example.com",		// リモートホスト
);

// 送信に対するホスト規制 (文字列一覧)
// この一覧にあるものと IP アドレスまたはリモートホスト、契約者固有 ID が一致すると送信不可になります。
// 契約者固有 ID は docomo, kddi, softbank, emobile に対応しています。
// ワイルドカード (*, ?, [] など) も使用可能です
$config->denyWrite = array
(
	// 例:
	// "192.168.*",			// IP アドレス
	// "*.example.com",		// リモートホスト
	// "docomo:*",			// キャリア:契約者固有 ID
);

// 閲覧に対するホスト規制の例外 (文字列一覧)
// この一覧にあるものと IP アドレスまたはリモートホストが一致すると上の規制リストおよび BBQ に含まれていても閲覧可能になります。
// ワイルドカード (*, ?, [] など) も使用可能です
$config->allowRead = array
(
	// 例:
	// "192.168.*",			// IP アドレス
	// "*.example.com",		// リモートホスト
);

// 送信に対するホスト規制の例外 (文字列一覧)
// この一覧にあるものと IP アドレスまたはリモートホスト、契約者固有 ID が一致すると上の規制リストおよび BBQ に含まれていても送信可能になります。
// 契約者固有 ID は docomo, kddi, softbank, emobile に対応しています。
// ワイルドカード (*, ?, [] など) も使用可能です
$config->allowWrite = array
(
	// 例:
	// "192.168.*",			// IP アドレス
	// "*.example.com",		// リモートホスト
	// "docomo:*",			// キャリア:契約者固有 ID
);

// 契約者固有 ID を通知しない携帯端末からの送信を拒否するか (true/false)
// 契約者固有 ID は docomo, kddi, softbank, emobile に対応しています
$config->denyWriteFromMobileWithoutID = false;

// 検索を許可するか (true/false)
$config->useSearch = true;

// 検索ボックスのカスタムの検索先 (特定値)
// - null																	標準の検索を使用します。
// - array("http://localhost/", "query")									http://localhost/ というアドレスに、query というパラメータで検索文字列を渡します。
// - array("http://localhost/", "query", array("a" => "b", "c" => "d"))		http://localhost/?a=b&c=d というアドレスに、query というパラメータで検索文字列を渡します。
// カスタムの検索をセットする場合、useSearch で標準の検索を許可しない場合においても検索欄が出現します。
$config->customSearch = null;

// 本文も検索の対象として登録するか (true/false)
// 投稿時に本文も検索インデックスに登録するかどうかを指定します。
// 投稿に時間がかかったり、ログ変換に失敗したりするようであれば false にすることを推奨します。
// すでに投稿されている作品に対しては反映されません
$config->registerBodyToSearchIndex = true;

// 検索インデックスへ登録する最大文字数 (整数)
// 検索インデックスへ登録する作品名、名前、本文などのテキストの最大文字数を指定します。
// 例えば本文がこの文字数以上の長さである場合、それ以降のテキストは検索対象になりません。
// 投稿に時間がかかったり、ログ変換に失敗したりするようであれば値を小さくすることを推奨します。
// -1 の場合、制限せず全文を登録します
$config->maximumSearchIndexLength = 1024;

// MySQL 時の検索インデックスの N-gram 長 (整数)
// MySQL 使用時の検索で使用可能な検索ワードの最小の長さを指定します。既定値は 4 です。最小値は 2 です。
// 数値が大きくなると検索インデックスの要求サイズが増えます。
// MySQL の既定の設定では 4 です。これを変更するには MySQL の設定ファイルを ft_min_word_len=1 などに変更する必要があります。
// 詳細については、以下の URL をご参照ください。
// http://dev.mysql.com/doc/refman/4.1/ja/fulltext-fine-tuning.html
// ft_min_word_len の値を下回る値を指定した場合、検索に何もヒットしません。
// この値を変更した場合、検索インデックスの再生成が必要です。
$config->mysqlSearchNgramLength = 4;

// MySQL 時の検索において N-gram 長を満たさない検索ワードに対して前方一致を使用するか (true/false)
// true にすることで ft_min_word_len を変更不可能な環境においても 3 文字以下の文字にマッチしますが、
// 作品や一致件数が増えると重くなることがあります。
// この値を変更した場合でも検索インデックスの再生成は必要ありません
$config->mysqlSearchUseHeadMatching = true;

/*
 * データストアに関する設定
 */
 
// データストアの種類 (特定値)
// - new SQLiteDataStore()			SQLite を使用して、ファイルにデータを保存します。デフォルトでは store/ ディレクトリに保存されます。
// - new SQLiteDataStore("foo/")	SQLite を使用して、ファイルにデータを保存します。この場合 foo/ ディレクトリに保存されます。
// - new MySQLDataStore(...)		MySQL を使用して、データベースにデータを保存します。詳細については以下の例を参照してください。
$config->dataStore = new SQLiteDataStore();
// MySQL を使用する場合は、以下のいずれかの形式に設定してください
// - new MySQLDataStore("データベース名", array("ホスト名", 3306), "ユーザ名", "パスワード");
// - new MySQLDataStore("データベース名", "UNIX ソケットパス", "ユーザ名", "パスワード");

// セッションをデータストアに保存するか (true/false)
// true の場合、データベースにセッションを保存し管理します。
// false の場合、PHP の通常のセッションの設定を使用します。
$config->storeSessionIntoDataStore = true;

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

// 枠色を指定できるかどうか (true/false)
$config->borderEnabled = true;

// 枠色パレットの一覧 (HTML 色一覧)
$config->borderMap = array
(
	"#0c0c0c",
	"#05057f",
	"#057f05",
	"#7f7f05",
	"#7f0505",
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

// 作品およびコメントの投稿者名における禁止ワード (文字列一覧)
$config->disallowedWordsForName = array
(
	// 例:
	// "NG",
);

// 禁止ワードにより投稿がエラーになったとき、原因となったワードを表示するか (true/false)
$config->showDisallowedWords = false;

// 管理者ログイン時の投稿は禁止ワードが含まれていてもエラーにしない (true/false)
$config->ignoreDisallowedWordsWhenAdmin = true;

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
	'<a href="' . Visualizer::actionHref("recent") . '">閲覧履歴</a>',
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

// 本文がページ分割されている場合最後のページのみにコメントを表示するか (true/false)
$config->showCommentsOnLastPageOnly = true;

// コメントおよび簡易評価両方が有効な場合の既定の評価方法 (特定値)
// - Configuration::EVAL_BOTH		タブにせず両方表示します。
// - Configuration::EVAL_POINT		簡易評価タブを既定にします。
// - Configuration::EVAL_COMMENT	コメントタブを既定にします。
$config->defaultEvaluator = Configuration::EVAL_COMMENT;

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
$config->showTweetButton = array
(
	Configuration::ON_AUTHOR => true,		// 作者別一覧に表示するか
	Configuration::ON_TAG => true,			// タグ別一覧に表示するか
	Configuration::ON_ENTRY => true,		// 作品に表示するか
);

// 作品の Tweet Button のテキスト (文字列)
// 以下のキーワードは次の項目に変換されます:
// - [id]			作品番号
// - [subject]		作品集番号
// - [title]		作品名
// - [name]			名前
// 例: "[title] 作者: [name]"
// null または空欄の場合ページタイトルになります
$config->entryTweetButtonText = "";

// 作品の Tweet Button のハッシュタグ (文字列)
// コンマ区切りで複数の指定が可能です。# は含めないでください。
// 以下のキーワードは次の項目に変換されます:
// - [id]			作品番号
// - [subject]		作品集番号
// - [title]		作品名
// - [name]			名前
// null または空欄の場合ハッシュタグは付加されません
$config->entryTweetButtonHashtags = "";

// 分類タグの Tweet Button のテキスト (文字列)
// 以下のキーワードは次の項目に変換されます:
// - [tag]			分類タグ
// null または空欄の場合ページタイトルになります
$config->tagTweetButtonText = "";

// 分類タグの Tweet Button のハッシュタグ (文字列)
// コンマ区切りで複数の指定が可能です。# は含めないでください。
// 以下のキーワードは次の項目に変換されます:
// - [tag]			分類タグ
// null または空欄の場合ハッシュタグは付加されません
$config->tagTweetButtonHashtags = "";

// タグの Tweet Button のテキスト (文字列)
// 以下のキーワードは次の項目に変換されます:
// - [author]		名前
// null または空欄の場合ページタイトルになります
$config->authorTweetButtonText = "";

// タグの Tweet Button のハッシュタグ (文字列)
// コンマ区切りで複数の指定が可能です。# は含めないでください。
// 以下のキーワードは次の項目に変換されます:
// - [author]		名前
// null または空欄の場合ハッシュタグは付加されません
$config->authorTweetButtonHashtags = "";

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

// Megalith ログの評価数をコメント数として取り込むかどうか (true/false)
// Megalith で得点制を使っておらず評価数のすべてがコメントであることが確定しており、
// convertOnDemand = true 時にコメント数が 0 と表示されてしまうときなどにお使いください
$config->importCompositeEvalsAsCommentCount = false;

// 設定終了
unset($config);
?>