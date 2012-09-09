megalopolis.index =
{
	setSortMenu: function()
	{
		function textSort(x, y)
		{
			return [x, y].sort()[0] == x ? -1 : 1;
		}
		
		var sortMapping =
		{
			title: textSort,
			name: textSort,
			size: function(x, y) { y.replace("KB", "") - x.replace("KB", "") },
			evaluationCount: function(x, y)
			{
				var allEval = y.replace(/^[0-9]+\//, "") - x.replace(/^[0-9]+\//, "");
				
				return allEval != 0 ? allEval : x.indexOf("/") == -1 ? 0 : y.replace(/\/[0-9]+$/, "") - x.replace(/\/[0-9]+$/, "");
			},
			dateTime: function(x, y) { return textSort(y, x); }
		};
		
		$(".index[id='sort']:last").one("pageshow", function()
		{
			$("li a", this).click(function()
			{
				var sort = $(this).attr("href").substr(6);
				var ul = $("ul.entries:last");
				
				ul.append(ul.children("li").sort(function(x, y)
				{
					var xt = $("." + sort, x).text();
					var yt = $("." + sort, y).text();
					
					return sortMapping[sort]
						? sortMapping[sort](xt, yt)
						: yt - xt;
				}));
				ul.append(ul.children("li.nextpage"));
				$(".ui-li-aside>span", ul).css("display", "none");
				$(".ui-li-aside>span." + sort, ul).css("display", "inline");
				
				history.back();
				
				return false;
			});
		});
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
	}
};