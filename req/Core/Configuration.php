<?php
class Configuration
{
	static Configuration $instance;
	
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
	
	public bool $debug = false;
	
	/*
	 * 全体に関する設定
	 */
	public string $title = "Megalopolis";
	public ?string $adminHash = null;
	public bool $utilsEnabled = false;
	public bool $htaccessAutoConfig = false;
	/** @var Configuration::LINK_* */
	public int $linkType = self::LINK_AUTO;
	public bool $useOutputCompression = true;
	/** @var Configuration::BBQ_* */
	public int $useBBQ = self::BBQ_NONE;
	/** @var string[] */
	public array $denyRead = array();
	/** @var string[] */
	public array $denyWrite = array();
	/** @var string[] */
	public array $allowRead = array();
	/** @var string[] */
	public array $allowWrite = array();
	public bool $denyWriteFromMobileWithoutID = false;
	public bool $useSearch = true;
	/** @var (string|string[])[]|null */
	public string|array|null $customSearch = null;
	public bool $registerBodyToSearchIndex = true;
	public int $maximumSearchIndexLength = 1024;
	public int $mysqlSearchNgramLength = 4;
	public bool $mysqlSearchUseHeadMatching = false;
	
	/*
	 * データストアに関する設定
	 */
	public ?DataStore $dataStore = null;
	public bool $storeSessionIntoDataStore = true;
	
	/*
	 * 評価に関する設定
	 */
	public bool $useComments = true;
	/** @var int[] */
	public array $pointMap = array();
	/** @var int[] */
	public array $commentPointMap = array();
	
	/*
	 * 投稿に関する設定
	 */
	public bool $adminOnly = false;
	public string $defaultName = "名前が無い程度の能力";
	/** @var array<(Configuration::ON_ENTRY | Configuration::ON_COMMENT), bool> */
	public array $requireName = array
	(
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	/** @var array<(Configuration::ON_ENTRY | Configuration::ON_COMMENT), bool> */
	public array $requirePassword = array
	(
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	public ?string $postPassword = null; 
	public int $maxTags = 10;
	public bool $foregroundEnabled = true;
	/** @var string[] */
	public array $foregroundMap = array
	(
		"#0c0c0c",
		"#05057f",
		"#057f05",
		"#7f7f05",
		"#7f0505",
	);
	public bool $backgroundEnabled = true;
	/** @var string[] */
	public array $backgroundMap = array
	(
		"#f5f5f5",
		"#f5f5ff",
		"#f5fff5",
		"#fffff5",
		"#fff5f5",
	);
	public bool $backgroundImageEnabled = false;
	/** @var string[] */
	public array $backgroundImageMap = array();
	public bool $borderEnabled = true;
	/** @var string[] */
	public array $borderMap = array
	(
		"#0c0c0c",
		"#05057f",
		"#057f05",
		"#7f7f05",
		"#7f0505",
	);
	public int $subjectSplitting = 100;
	/** @var string[] */
	public array $disallowedWordsForEntry = array();
	/** @var string[] */
	public array $disallowedWordsForComment = array();
	/** @var string[] */
	public array $disallowedWordsForName = array();
	/** @var string[] */
	public array $allowedTags = array
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
	/** @var string[] */
	public array $disallowedTags = array
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
	/** @var string[] */
	public array $disallowedAttributes = array
	(
		"regex:on.*"
	);
	public bool $showDisallowedWords = false;
	public bool $ignoreDisallowedWordsWhenAdmin = true;
	public int $minBodySize = 0;
	public int $maxBodySize = 0;
	public bool $useSummary = true;
	public int $maxSummaryLines = 0;
	public int $maxSummarySize = 0;
	
	/*
	 * 表示に関する設定
	 */
	public ?string $skin = null;
	/** @var Configuration::ORDER_* */
	public int $subjectOrder = self::ORDER_DESCEND;
	/** @var Configuration::RATE_* */
	public int $rateType = self::RATE_AVERAGE;
	public int $searchPaging = 30;
	public int $tagListing = 100;
	public int $maxHistory = 30;
	public ?string $head = null;
	public ?string $notes = null;
	public bool $showFooterVersion = true;
	/** @var string[] */
	public array $footers = array();
	/** @var Configuration::LIST_* */
	public int $listType = self::LIST_DOUBLE;
	public int $updatePeriod = 3;
	public bool $showHeaderInsideBorder = false;
	public bool $showCommentsOnLastPageOnly = true;
	/** @var Configuration::EVAL_* */
	public int $defaultEvaluator = self::EVAL_POINT;
	/** @var bool[] */
	public array $showTitle = array
	(
		self::ON_SUBJECT => true,
	);
	/** @var bool[] */
	public array $showName = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	/** @var bool[] */
	public array $showTags = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	/** @var bool[] */
	public array $showSummary = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	/** @var bool[] */
	public array $showReadCount = array
	(
		self::ON_SUBJECT => false,
		self::ON_ENTRY => true,
	);
	/** @var bool[] */
	public array $showPoint = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
		self::ON_COMMENT => true,
	);
	/** @var bool[] */
	public array $showRate = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	/** @var bool[] */
	public array $showComment = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	/** @var bool[] */
	public array $showSize = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	/** @var bool[] */
	public array $showPages = array
	(
		self::ON_SUBJECT => true,
		self::ON_ENTRY => true,
	);
	/** @var bool[]|null */
	public ?array $showTweetButton = null;
	public ?string $entryTweetButtonText = null;
	public ?string $entryTweetButtonHashtags = null;
	public ?string $tagTweetButtonText = null;
	public ?string $tagTweetButtonHashtags = null;
	public ?string $authorTweetButtonText = null;
	public ?string $authorTweetButtonHashtags = null;
	
	/*
	 * ログの変換に関する設定
	 */
	public int $convertDivision = 50;
	public bool $convertOnDemand = false;
	public bool $importCompositeEvalsAsCommentCount = false;
	
	function usePoints(): bool
	{
		return !empty($this->pointMap);
	}
	
	function useCommentPoints(): bool
	{
		return !empty($this->commentPointMap);
	}
	
	function useAnyPoints(): bool
	{
		return $this->usePoints()
			|| $this->useCommentPoints();
	}
}
?>
