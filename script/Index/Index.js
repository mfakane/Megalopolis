megalopolis.index =
{
	loadSubjectPager: function()
	{
		var form = $("form").last();
		
		$("button", form).remove();
		$("select", form).change(function()
		{
			location.href = form.prop("action") + $(this).val();
		});
	},
	loadDropDown: function(useTitle, useName, usePageCount, useReadCount, useSize, useEvaluationCount, useCommentCount, usePoints, useRate, useSummary, defaultStyle)
	{
		if ($.browser.msie &&
			$.browser.version < 8)
			return;
		
		var h1 = $("header+h1:first");
		var entries = (function()
		{
			var elem = null;
			
			return function() { return elem ? elem : elem = $("#entries")[0]; };
		})();
		var lastSort =
		{
			label: "投稿日時",
			value: "dateTime",
			selector: function(x, y)
			{
				var arr = $.map([x, y], function(_) { return _.find(".dateTime").find(".value").text() - 0; });
				
				return arr[0] - arr[1];
			}
		};
		var ascending = false;
		var dropDown = $("<ul />")
			.append($.map
			([
				!useTitle ? null :
				{
					label: "作品名",
					value: "title",
					selector: function(x, y)
					{
						var arr = $.map([x, y], function(_) { return $("h2>a", _).text(); });
						
						return arr[0] == arr.sort()[0] ? -1 : 1;
					}
				},
				!useName ? null :
				{
					label: "作者",
					value: "name",
					selector: function(x, y)
					{
						var arr = $.map([x, y], function(_) { return $(".name", _).text(); });
						
						return arr[0] == arr.sort()[0] ? -1 : 1;
					}
				},
				!usePageCount ? null :
				{
					label: "ページ",
					value: "pageCount"
				},
				!useReadCount ? null :
				{
					label: "閲覧",
					value: "readCount"
				},
				!useSize ? null :
				{
					label: "サイズ",
					value: "size",
					selector: function(x, y)
					{
						return $(".size", x).text().replace(/KB$/, "") - $(".size", y).text().replace(/KB$/, "");
					}
				},
				!useEvaluationCount ? null :
				{
					label: "評価",
					value: "evaluationCount",
					selector: function(x, y)
					{
						var a = $(".evaluationCount", x).text();
						var b = $(".evaluationCount", y).text();
						var allEval = a.replace(/^[0-9]+\//, "") - b.replace(/^[0-9]+\//, "");
						
						return allEval != 0 ? allEval : a.indexOf("/") == -1 ? 0 : a.replace(/\/[0-9]+$/, "") - b.replace(/\/[0-9]+$/, "");
					}
				},
				!useCommentCount ? null :
				{
					label: "コメント",
					value: "commentCount"
				},
				!usePoints ? null :
				{
					label: "POINT",
					value: "points"
				},
				!useRate ? null :
				{
					label: "Rate",
					value: "rate"
				},
				lastSort,
				{
					label: "更新日時",
					value: "lastUpdate",
					selector: function(x, y)
					{
						var arr = $.map([x, y], function(_) { return _.find(".lastUpdate").find(".value").text() - 0; });
						
						return arr[0] - arr[1];
					}
				}
			],
			function(_)
			{
				if (_ != null)
				{
					var item = $("<li />")
						.text(_.label)
						.click(function()
						{
							if (lastSort == _)
								ascending = !ascending;
							
							lastSort = _;
							megalopolis.index.sort(control, ascending, _.selector
								? _.selector
								: function(x, y) { return $("." + _.value, x).text() - $("." + _.value, y).text(); });
							label.text(_.label);
							
							return false;
						});
					
					$(function()
					{
						$("#entries")
							.find("th." + _.value)
							.click(function()
							{
								item.click();
								
								return false;
							})
							.addClass("clickable");
					});
					
					return item[0];
				}
			}))
			.hide();
		var label = $("<span />")
			.text("投稿日時");
		var button = $("<div />")
			.addClass("button");
		var control = $("<div />")
			.addClass("dropDown")
			.append(label)
			.append(button)
			.append(dropDown)
			.appendTo(h1)
			.mouseenter(function()
			{
				control.addClass("open");
				dropDown.show();
				
				return false;
			})
			.mouseleave(function()
			{
				control.removeClass("open");
				dropDown.hide();
				
				return false;
			})
			.click(function()
			{
				ascending = !ascending;
				megalopolis.index.sort(control, ascending, lastSort.selector
					? lastSort.selector
					: function(x, y) { return $("." + lastSort.value, x).text() - $("." + lastSort.value, y).text(); });
				
				return false;
			});
		
		var styleMap =
		[
			{
				name: "二列表示",
				value: "double"
			},
			{
				name: "テーブル",
				value: "single"
			}
		];
		var currentStyleValue = megalopolis.mainCookie("ListType");
		var currentStyle = $.grep(styleMap, function(_) { return _.value == currentStyleValue });
		
		currentStyle = currentStyle.length ? currentStyle[0] : styleMap[defaultStyle];
		
		var visibilityCookie = $.grep([megalopolis.mainCookie("ListVisibility"), "readCount,size,commentCount,evaluationCount,points,rate,dateTime"], function(_) { return _; })[0].split(",");
		var styleControl = $("<div />")
			.addClass("dropDown")
			.append($("<span />").text(currentStyle.name))
			.append($("<ul />")
				.append($.map
				(
					styleMap,
					function(_)
					{
						return $("<li />")
							.addClass("listType")
							.addClass(_ == currentStyle ? "selected" : null)
							.text(_.name)
							.append($("<div />").addClass("button"))
							.click(function()
							{
								megalopolis.mainCookie("ListType", _.value);
								location.reload();
							})[0];
					}
				))
				.append($.map
				(
					[
						!usePageCount ? null :
						{
							label: "ページ",
							value: "pageCount"
						},
						!useReadCount ? null :
						{
							label: "閲覧",
							value: "readCount"
						},
						!useSize ? null :
						{
							label: "サイズ",
							value: "size"
						},
						!useEvaluationCount ? null :
						{
							label: "評価",
							value: "evaluationCount"
						},
						!useCommentCount ? null :
						{
							label: "コメント",
							value: "commentCount"
						},
						!usePoints ? null :
						{
							label: "POINT",
							value: "points"
						},
						!useRate ? null :
						{
							label: "Rate",
							value: "rate"
						},
						{
							label: "投稿日時",
							value: "dateTime"
						},
						{
							label: "更新日時",
							value: "lastUpdate"
						},
						!useSummary || currentStyleValue != "single" ? null :
						{
							label: "概要",
							value: "summary"
						}
					],
					function(_)
					{
						if (_ != null)
							return $("<li />")
								.addClass("listVisibility")
								.addClass($.inArray(_.value, visibilityCookie) != -1 ? "selected" : null)
								.text(_.label)
								.append($("<div />").addClass("button"))
								.click(function()
								{
									var elem = $(this);
									var idx = $.inArray(_.value, visibilityCookie);
									var table = $("table", entries());
									
									elem.toggleClass("selected");
									
									if (_.value == "summary")
									{
										if (!elem.hasClass("selected"))
											table.find(".summary").addClass("hidden");
										else
											table.find(".summary").removeClass("hidden");
									}
									else
									{
										var tagRows = table.find("tr.tags").find("td");
										var fromColSpan = tagRows.prop("colspan");
										var articles = $("article", entries());
										
										articles.find("dd." + _.value).each(function(k, v)
										{
											$([$(v), $(v).prev("dt")]).toggleClass("hidden");
										});
										articles.find("dl").each(function(k, v)
										{
											var elems = $("dt", v);
											var firstVisible = elems.not(".hidden").first();
											
											elems.removeClass("firstChild");
											firstVisible.addClass("firstChild");
										});
										articles.find("time").each(function(k, v)
										{
											var e = $(v);
											
											if (e.hasClass(_.value))
												e.toggleClass("hidden");
										});
										
										$([table.find("th." + _.value), table.find("td." + _.value)]).each(function(k, v)
										{
											var elem = $(this);
											
											if (elem.hasClass("hidden"))
												tagRows.prop("colspan", fromColSpan + 1);
											else
												tagRows.prop("colspan", fromColSpan - 1);
											
											elem.toggleClass("hidden");
										});
									}
									
									if (idx != -1)
										delete visibilityCookie[idx];
									else
										visibilityCookie.push(_.value);
									
									megalopolis.mainCookie("ListVisibility", visibilityCookie.join(",").replace(/,{2,}/g, ","));
									
									return false;
								})[0];
					}
				))
				.hide())
			.mouseenter(function()
			{
				styleControl.addClass("open");
				$("ul", styleControl).show();
				
				return false;
			})
			.mouseleave(function()
			{
				styleControl.removeClass("open");
				$("ul", styleControl).hide();
				
				return false;
			})
			.appendTo(h1);
	},
	sort: function(control, ascending, selector)
	{
		if (ascending)
			control.addClass("ascending");
		else
			control.removeClass("ascending");
		
		var div = $("#entries");
		var tbody = div.find("table").find("tbody");
		
		div.append($("article", div).sort(function(x, y)
		{
			return ascending ? selector($(x), $(y)) : selector($(y), $(x));
		}));
		
		var list = $.map(tbody.find("tr.article").map(function()
		{
			return $(this).next("tr.tags").andSelf();
		}).sort(function(x, y)
		{
			return ascending ? selector($(x[0]), $(y[0])) : selector($(y[0]), $(x[0]));
		}), function(_)
		{
			return [_[0], _[1]];
		});
		
		tbody.append(list);
	},
	showSummary: function()
	{
		var tbody = $("#entries").find("table").find("tbody");
		
		tbody.click(function(e)
		{
			var tagName = e.target.tagName.toLowerCase();
			
			if (tagName != "a" &&
				tagName != "input" ||
				e.target.className == "summaryButton")
			{
				var row = $(e.target).parents("tr");
				var summary = (row.hasClass("tags") ? row : row.next("tr.tags")).find("p");
				
				summary.toggleClass("hidden");
			}
		});
	}
};

$(function()
{
	$("#moveButton").click(function()
	{
		return window.confirm("本当に作品を移動してよろしいですか？");
	});
	$("#unpostButton").click(function()
	{
		return window.confirm("本当に作品を削除してよろしいですか？");
	});
});
