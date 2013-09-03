<?php
class Configuration
{
	/**
	 * @var Configuration
	 */
	static $instance;
	
	const LINK_AUTO = 0;
	const LINK_REWRITE = 1;
	const LINK_PATH = 2;
	const LINK_QUERY = 3;
	const ORDER_ASCEND = Board::ORDER_ASCEND;
	const ORDER_DESCEND = Board::ORDER_DESCEND;
	const RATE_FIVE = 0;
	const RATE_AVERAGE = 1;
	const BBQ_NONE = 0;
	const BBQ_READ = 1;
	const BBQ_WRITE = 2;
	const BBQ_READWRITE = 3;
	const ON_ENTRY = "Entry";
	const ON_COMMENT = "Comment";
	const ON_SUBJECT = "Subject";
	const ON_AUTHOR = "Author";
	const ON_TAG = "Tag";
	const LIST_DOUBLE = 0;
	const LIST_SINGLE = 1;
	const EVAL_BOTH = -1;
	const EVAL_POINT = 0;
	const EVAL_COMMENT = 1;
	
	public $debug = false;
	
	/*
	 * 全体に関する設定
	 */
	public $title = "Megalopolis";
	public $adminHash = null;
	public $utilsEnabled = false;
	public $htaccessAutoConfig = false;
	public $linkType = self::LINK_AUTO;
	public $useOutputCompression = true;
	public $useBBQ = self::BBQ_NONE;
	public $denyRead = array();
	public $denyWrite = array();
	public $allowRead = array();
	public $allowWrite = array();
	public $denyWriteFromMobileWithoutID = false;
	public $useSearch = true;
	public $customSearch = null;
	public $registerBodyToSearchIndex = true;
	public $maximumSearchIndexLength = 1024;
	public $mysqlSearchNgramLength = 4;
	public $mysqlSearchUseHeadMatching = false;
	
	/*
	 * データストアに関する設定
	 */
	/**
	 * @var DataStore
	 */
	public $dataStore;
	public $storeSessionIntoDataStore = true;
	
	/*
	 * 評価に関する設定
	 */
	public $useComments = true;
	public $pointMap = array();
	public $commentPointMap = array();
	
	/*
	 * 投稿に関する設定
	 */
	public $adminOnly = false;
	public $defaultName = "名前が無い程度の能力";
	public $requireName = array
	(
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	public $requirePassword = array
	(
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	public $postPassword = null; 
	public $maxTags = 10;
	public $foregroundEnabled = true;
	public $foregroundMap = array
	(
		"#0c0c0c",
		"#05057f",
		"#057f05",
		"#7f7f05",
		"#7f0505",
	);
	public $backgroundEnabled = true;
	public $backgroundMap = array
	(
		"#f5f5f5",
		"#f5f5ff",
		"#f5fff5",
		"#fffff5",
		"#fff5f5",
	);
	public $backgroundImageEnabled = false;
	public $backgroundImageMap = array();
	public $borderEnabled = true;
	public $borderMap = array
	(
		"#0c0c0c",
		"#05057f",
		"#057f05",
		"#7f7f05",
		"#7f0505",
	);
	public $subjectSplitting = 100;
	public $disallowedWordsForEntry = array();
	public $disallowedWordsForComment = array();
	public $disallowedWordsForName = array();
	public $allowedTags = array
	(
		"a",
		"abbr",
		"address",
		"area",
		"article",
		"aside",
		"b",
		"basefont",
		"bdo",
		"big",
		"blockquote",
		"br",
		"caption",
		"center",
		"cite",
		"code",
		"col",
		"colgroup",
		"dd",
		"del",
		"details",
		"dfn",
		"dir",
		"div",
		"dl",
		"dt",
		"em",
		"font",
		"footer",
		"h1",
		"h2",
		"h3",
		"h4",
		"h5",
		"h6",
		"header",
		"hgroup",
		"hr",
		"i",
		"img",
		"ins",
		"li",
		"map",
		"nav",
		"ol",
		"p",
		"pre",
		"q",
		"rb",
		"rp",
		"rt",
		"ruby",
		"s",
		"samp",
		"section",
		"small",
		"span",
		"split",
		"strike",
		"strong",
		"sub",
		"summary",
		"sup",
		"table",
		"tbody",
		"td",
		"tfoot",
		"th",
		"thead",
		"time",
		"tr",
		"tt",
		"u",
		"ul",
		"var",
		"wbr",
	);
	public $disallowedTags = array
	(
		"html" => "div",
		"body" => "div",
		
		// head
		"base",
		"link",
		"meta",
		"style",
		"title",
		
		// scripting
		"script",
		
		// embedding
		"audio",
		"embed",
		"iframe",
		"object",
		"param",
		"video",
		
		// forms
		"button",
		"datalist",
		"fieldset",
		"form",
		"input",
		"keygen",
		"label",
		"legend",
		"meter",
		"optgroup",
		"option",
		"output",
		"progress",
		"select",
		"textarea",
		
		// obsolete
		"acronym",
		"applet",
		"bgsound",
		"blink",
		"comment",
		"dir",
		"frame",
		"frameset",
		"isindex",
		"listing",
		"marquee",
		"nextid",
		"nobr",
		"noembed",
		"noframes",
		"plaintext",
		"xmp",
	);
	public $disallowedAttributes = array
	(
		"regex:on.*"
	);
	public $showDisallowedWords = false;
	public $ignoreDisallowedWordsWhenAdmin = true;
	public $minBodySize = 0;
	public $maxBodySize = 0;
	public $useSummary = true;
	public $maxSummaryLines = 0;
	public $maxSummarySize = 0;
	
	/*
	 * 表示に関する設定
	 */
	public $skin = null;
	public $subjectOrder = self::ORDER_DESCEND;
	public $rateType = self::RATE_AVERAGE;
	public $searchPaging = 30;
	public $tagListing = 100;
	public $maxHistory = 30;
	public $head = null;
	public $notes = null;
	public $showFooterVersion = true;
	public $footers = array();
	public $listType = self::LIST_DOUBLE;
	public $updatePeriod = 3;
	public $showHeaderInsideBorder = false;
	public $showCommentsOnLastPageOnly = true;
	public $defaultEvaluator = self::EVAL_POINT;
	public $showTitle = array
	(
		self::ON_SUBJECT => true,
	);
	public $showName = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	public $showTags = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	public $showSummary = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	public $showReadCount = array
	(
		self::ON_SUBJECT => false,
		self::ON_ENTRY => true,
	);
	public $showPoint = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	public $showRate = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	public $showComment = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	public $showSize = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	public $showPages = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	public $showTweetButton = false;
	public $entryTweetButtonText = null;
	public $entryTweetButtonHashtags = null;
	public $tagTweetButtonText = null;
	public $tagTweetButtonHashtags = null;
	public $authorTweetButtonText = null;
	public $authorTweetButtonHashtags = null;
	
	/*
	 * ログの変換に関する設定
	 */
	public $convertDivision = 50;
	public $convertOnDemand = false;
	public $importCompositeEvalsAsCommentCount = false;
	
	function usePoints()
	{
		return !empty($this->pointMap);
	}
	
	function useCommentPoints()
	{
		return !empty($this->commentPointMap);
	}
	
	function useAnyPoints()
	{
		return $this->usePoints()
			|| $this->useCommentPoints();
	}
}
?>