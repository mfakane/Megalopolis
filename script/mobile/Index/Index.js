megalopolis.index =
{
	textSort: function(x, y)
	{
		return [x, y].sort()[0] == x ? -1 : 1;
	},
	sortMapping:
	{
		title: function(x, y) { return megalopolis.index.textSort(x, y); },
		name: function(x, y) { return megalopolis.index.textSort(x, y); },
		size: function(x, y) { return x.replace("KB", "") - y.replace("KB", ""); },
		evaluationCount: function(x, y)
		{
			var allEval = y.replace(/^[0-9]+\//, "") - x.replace(/^[0-9]+\//, "");
			
			return allEval != 0 ? allEval : x.indexOf("/") == -1 ? 0 : y.replace(/\/[0-9]+$/, "") - x.replace(/\/[0-9]+$/, "");
		}
	},
	initSort: function(id)
	{
		$("#" + id).bind("pageinit", function()
		{
			megalopolis.index.setSort();
		});
	},
	setSort: function(sort)
	{
		sort = megalopolis.mainCookie("Sort", sort);
		
		if (sort)
		{
			var ul = $("ul.entries").last();
			
			if (sort == "dateTime")
				sort = "dateTimeValue";
			
			ul.append(ul.children("li").sort(function(x, y)
			{
				var xt = $("." + sort, x).text();
				var yt = $("." + sort, y).text();
				
				return megalopolis.index.sortMapping[sort]
					? megalopolis.index.sortMapping[sort](xt, yt)
					: yt - xt;
			}));
			ul.append(ul.children("li.nextpage"));
		}
	},
	timeToString: function(time)
	{
		function padLeft(str, len)
		{
			str = str + "";
			
			while (str.length < len)
				str = "0" + str;
			
			return str;
		}
		
		var date = (function(_) { _.setTime(time); return _; })(new Date());
		var diff = ($.now() - time) / 1000;
		var minute = 60;
		var hour = minute * 60;
		var day = hour * 24;

		if (diff < minute)
			return Math.floor(diff) + " 秒前";
		else if (diff < hour)
			return Math.floor(diff / minute) + " 分前";
		else if (diff < day)
			return Math.floor(diff / hour) + " 時間前";
		else if (diff < day * 3)
			return Math.floor(diff / day) + " 日前 " + padLeft(date.getHours(), 2) + ":" + padLeft(date.getMinutes(), 2);
		else
			return padLeft(date.getMonth() + 1, 2) + "/" + padLeft(date.getDate(), 2) + " " + padLeft(date.getHours(), 2) + ":" + padLeft(date.getMinutes(), 2);
	},
	renderSettings: function()
	{
		$("#verticalSwitch")
			.val(megalopolis.mainCookie("MobileVertical") || "yes")
			.change(function()
			{
				megalopolis.index.settingsChanged();
			})
			.slider("refresh");
	},
	settingsChanged: function()
	{
		megalopolis.mainCookie("MobileVertical", $("#verticalSwitch").val());
	}
};

$(document).bind("pageinit", function(e)
{
	var page = e.target;
	
	if (page.id == "home" || page.id == "search")
		megalopolis.index.setSort();
	else if (page.id == "config")
		megalopolis.index.renderSettings();
	else if (page.id == "sort")
		$("li a", page).click(function()
		{
			megalopolis.index.setSort($(this).data("column"));
			history.back();
			
			return false;
		});
});
