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
		size: function(x, y) { y.replace("KB", "") - x.replace("KB", "") },
		evaluationCount: function(x, y)
		{
			var allEval = y.replace(/^[0-9]+\//, "") - x.replace(/^[0-9]+\//, "");
			
			return allEval != 0 ? allEval : x.indexOf("/") == -1 ? 0 : y.replace(/\/[0-9]+$/, "") - x.replace(/\/[0-9]+$/, "");
		},
		dateTime: function(x, y) { return megalopolis.index.textSort(y, x); }
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
		
			ul.append(ul.children("li").sort(function(x, y)
			{
				var xt = $("." + sort, x).text();
				var yt = $("." + sort, y).text();
				
				return megalopolis.index.sortMapping[sort]
					? megalopolis.index.sortMapping[sort](xt, yt)
					: yt - xt;
			}));
			ul.append(ul.children("li.nextpage"));
			
			var aside = $(".ui-li-aside");
			
			aside.children("span", ul).not(".dateTime").css("display", "none");
			aside.children("." + (["title", "name", "dateTime"].indexOf(sort) == -1 ? sort : "evaluationCount"), ul).css("display", "inline");
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
	renderHistory: function(basePath)
	{
		var self = this;
		var ul = $("ul.entries:last");
		
		ul.append($.map(megalopolis.getHistory().reverse(), function(_)
		{
			return $("<li />")
				.append($("<a />")
					.attr("href", basePath + _.subject + "/" + _.id)
					.append
					(
						$("<h2 />").html(_.title),
						$("<p />").addClass("ul-li-aside").css({ "float": "right", "margin": "0px" }).text(self.timeToString(_.historySet)),
						$("<p />").addClass("name").html(_.name)
					))[0];
		}));
	},
	renderSettings: function()
	{
		$("#verticalSwitch").val(megalopolis.mainCookie("MobileVertical") || "yes").slider("refresh");
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
});
