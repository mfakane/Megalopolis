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
	loadDropDown: function(useTitle, useName, usePageCount, useReadCount, useSize, useEvaluationCount, useCommentCount, usePoints, useRate, defaultStyle)
	{
		if ($.browser.msie &&
			$.browser.version < 8)
			return;
		
		var lastSort =
		{
			label: "投稿日時",
			value: "dateTime",
			selector: function(x, y)
			{
				var arr = $.map([x, y], function(_) { return $(".value", _).text(); });
				
				return arr[0] == arr.sort()[0] ? -1 : 1;
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
				lastSort
			],
			function(_)
			{
				if (_ != null)
					return $("<li />")
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
						})[0];
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
			.appendTo($("header+h1"))
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
		
		var visibilityCookie = $.grep([megalopolis.mainCookie("ListVisibility"), !usePoints ? "pageCount,readCount,size,commentCount,dateTime" : "pageCount,readCount,size,evaluationCount,points,rate,dateTime"], function(_) { return _; })[0].split(",");
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
								location.href = location.href;
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
									var tagRows = $(".entries>table tr.tags>td");
									var fromColSpan = tagRows.prop("colspan");
									
									elem.toggleClass("selected");
									
									$(".entries>article dd." + _.value).each(function(k, v)
									{
										$([$(v), $(v).prev("dt")]).toggleClass("hidden");
									});
									$(".entries>article dl").each(function(k, v)
									{
										var elems = $("dt", v);
										var firstVisible = elems.not(".hidden").first();
										
										elems.removeClass("firstChild");
										firstVisible.addClass("firstChild");
									});
									
									$(".entries>table th." + _.value + ", .entries>table td." + _.value).each(function(k, v)
									{
										var elem = $(this);
										
										if (elem.hasClass("hidden"))
											tagRows.prop("colspan", fromColSpan + 1);
										else
											tagRows.prop("colspan", fromColSpan - 1);
										
										elem.toggleClass("hidden");
									});
									
									if (idx != -1)
										delete visibilityCookie[idx];
									else
										visibilityCookie.push(_.value);
									
									megalopolis.mainCookie("ListVisibility", visibilityCookie.join(",").replace(/,{2,}/g, ","));
									
									return false;
								})[0];
					}
				))
				.append($.map
				(
					[
						{
							label: "投稿日時",
							value: "dateTime"
						},
						{
							label: "更新日時",
							value: "lastUpdate"
						}
					],
					function(_)
					{
						return $("<li />")
							.addClass("dateType")
							.addClass($.inArray(_.value, visibilityCookie) != -1 ? "selected" : null)
							.text(_.label)
							.append($("<div />").addClass("button"))
							.click(function()
							{
								var elem = $(this);
								var dateTimeIndex = $.inArray("dateTime", visibilityCookie);
								var lastUpdateIndex = $.inArray("lastUpdate", visibilityCookie);
								
								$(".dropDown ul li.dateType").each(function(k, v)
								{
									var e = $(v);
									
									if (e.text().indexOf(_.label) != -1)
										e.addClass("selected");
									else
										e.removeClass("selected");
								});
								$(".entries>article time").each(function(k, v)
								{
									var e = $(v);
									
									if (e.hasClass(_.value))
										e.removeClass("hidden");
									else
										e.addClass("hidden");
								});
								$(".entries>table .dateTime, .entries>table .lastUpdate").each(function(k, v)
								{
									var e = $(v);
									
									if (e.hasClass(_.value))
										e.removeClass("hidden");
									else
										e.addClass("hidden");
								});
								
								if (dateTimeIndex != -1)
									delete visibilityCookie[dateTimeIndex];
								
								if (lastUpdateIndex != -1)
									delete visibilityCookie[lastUpdateIndex];
								
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
			.appendTo($("header+h1"));
	},
	sort: function(control, ascending, selector)
	{
		if (ascending)
			control.addClass("ascending");
		else
			control.removeClass("ascending");
		
		var div = $(".entries");
		
		div.append($("article", div).sort(function(x, y)
		{
			return ascending ? selector($(x), $(y)) : selector($(y), $(x));
		}));
		
		var list = $.map($("table>tbody>tr.article", div).map(function()
		{
			return $([$(this), $(this).next("tr.tags")]);
		}).sort(function(x, y)
		{
			return ascending ? selector($(x[0]), $(y[0])) : selector($(y[0]), $(x[0]));
		}), function(_)
		{
			return [_[0], _[1]];
		});
		
		$("table>tbody", div).append(list);
	},
	showSummary: function()
	{
		var tagRow = $("tr.tags").last();
		var button = $('<a href="javascript:void(0);" />').addClass("summaryButton").text("[概要]").prependTo($("td", tagRow));
		var rows = $([tagRow.prev("tr")[0], tagRow[0]]);
		
		$("a, input", rows).click(function(e)
		{
			if (this != button[0])
				e.stopPropagation();
		});
		
		rows.mouseenter(function()
		{
			rows.addClass("hover");
		})
		.mouseleave(function()
		{
			rows.removeClass("hover");
		})
		.click(function()
		{
			var summary = $("p", tagRow);
			
			if (summary.hasClass("hidden"))
				summary.removeClass("hidden");
			else
				summary.addClass("hidden");
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
