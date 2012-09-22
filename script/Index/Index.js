$(function()
{
	var h1 = $("header+h1").first();
	var entries = $("#entries");
	var listType = !entries.length ? null : entries.data("type");
	var use = !entries.length ? null : $.extend((function(arr, obj) { return $.each(obj, function(k) { obj[k] = $.inArray(k, arr) >= 0; }); })
	(
		entries.data("use").split(" "),
		{
			title: null,
			name: null,
			pageCount: null,
			readCount: null,
			size: null,
			evaluationCount: null,
			commentCount: null,
			size: null,
			evaluationCount: null,
			commentCount: null,
			points: null,
			rate: null,
			summary: null
		}
	),
	{
		dateTime: true,
		lastUpdate: true,
	});
	var loadSortDropDown = function()
	{
		var selector =
		{
			numeric: function(elem)
			{
				return function(cls, x, y) { return x.find(elem || "." + cls).text() - y.find(elem || "." + cls).text(); };
			},
			dateTime: function(elem)
			{
				return function(cls, x, y) { return x.find(elem || "." + cls).data("unixtime") - y.find(elem || "." + cls).data("unixtime"); };
			},
			text: function(elem)
			{
				return function(cls, x, y)
				{
					var arr = [x.find(elem || "." + cls).text(), y.find(elem || "." + cls).text()];
					
					return arr[0] == arr.sort()[0] ? -1 : 1;
				};
			},
			size: function(elem)
			{
				return function(cls, x, y) { return x.find(elem || "." + cls).text().replace(/KB$/, "") - y.find(elem || "." + cls).text().replace(/KB$/, ""); };
			},
			evaluation: function(elem)
			{
				return function(cls, x, y)
				{
					var a = $(elem || "." + cls, x).text();
					var b = $(elem || "." + cls, y).text();
					var allEval = a.replace(/^[0-9]+\//, "") - b.replace(/^[0-9]+\//, "");
					
					return allEval != 0 ? allEval : a.indexOf("/") == -1 ? 0 : a.replace(/\/[0-9]+$/, "") - b.replace(/\/[0-9]+$/, "");
				};
			}
		};
		var sortItems = (function(obj)
		{
			return $.each(obj, function(k, v)
			{
				if (use[k])
					obj[k] =
					{
						label: v.label || v,
						value: k,
						selector: v.selector ? function(x, y) { return v.selector(k, x, y); } : function(x, y) { return selector.numeric()(k, x, y); }
					};
				else
					delete obj[k];
			});
		})
		({
			title:
			{
				label: "作品名",
				selector: selector.text("h2>a")
			},
			name:
			{
				label: "作者",
				selector: selector.text(),
			},
			pageCount: "ページ",
			size:
			{
				label: "サイズ",
				selector: selector.size(),
			},
			readCount: "閲覧",
			evaluationCount:
			{
				label: "評価",
				selector: selector.evaluation(),
			},
			commentCount: "コメント",
			points: "POINT",
			rate: "Rate",
			dateTime:
			{
				label: "投稿日時",
				selector: selector.dateTime(),
			},
			lastUpdate:
			{
				label: "更新日時",
				selector: selector.dateTime(),
			}
		});
		var lastSort = sortItems.dateTime;
		var ascending = false;
		
		var control = $('<div class="dropDown"><span>投稿日時</span><div class="button"></div><ul></ul></div>');
		var label = control.find("span");
		var dropDown = control.find("ul").hide().append($.map(sortItems, function(v)
		{
			var item = $("<li />")
				.text(v.label)
				.click(function()
				{
					if (lastSort == v)
						ascending = !ascending;
					
					lastSort = v;
					sort(v.selector);
					label.text(v.label);
					
					return false;
				});
			
			if (listType == "single")
				$(function()
				{
					entries
						.find("th." + v.value)
						.click(function()
						{
							item.click();
							
							return false;
						})
						.addClass("clickable");
				});
			
			return item;
		}));
		var sort = function(selector)
		{
			selector = selector || lastSort.selector;
			
			if (ascending)
				control.addClass("ascending");
			else
				control.removeClass("ascending");
			
			if (listType == "single")
			{
				var tbody = entries.find("tbody");
				
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
			}
			else
				entries.append(entries.find("article").sort(function(x, y)
				{
					return ascending ? selector($(x), $(y)) : selector($(y), $(x));
				}));
		};
		
		control.mouseenter(function()
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
			sort();
			
			return false;
		})
		.appendTo(h1);
	};
	var loadVisibilityDropDown = function()
	{
		var styleMap =
		{
			"double": "二列表示",
			"single": "テーブル"
		};
		var visibilityMap = (function(obj) { return $.each(obj, function(k, v) { if (!use[k]) delete obj[k]; }); })
		({
			pageCount: "ページ",
			size: "サイズ",
			readCount: "閲覧",
			evaluationCount: "評価",
			commentCount: "コメント",
			points: "POINT",
			rate: "Rate",
			dateTime: "投稿日時",
			lastUpdate: "更新日時",
			summary: "概要"
		});
		var visible = (megalopolis.mainCookie("ListVisibility") || "readCount,size,commentCount,evaluationCount,points,rate,dateTime").split(",");
		
		if (listType != "single")
			delete visibilityMap["summary"];
		
		var control = $('<div class="dropDown"><span></span><ul></ul></div>');
		var label = control.find("span").text(styleMap[listType]);
		var dropDown = control.find("ul").hide();
		
		dropDown.append($.map(styleMap, function(v, k)
		{
			return $('<li>' + v + '<div class="button"></div></li>')
				.addClass("listType")
				.addClass(k == listType ? "selected" : null)
				.click(function()
				{
					megalopolis.mainCookie("ListType", k);
					location.reload();
					
					return false;
				});
		}));
		dropDown.append($.map(visibilityMap, function(v, k)
		{
			return $('<li>' + v + '<div class="button"></div></li>')
				.addClass("listVisibility")
				.addClass($.inArray(k, visible) >= 0 ? "selected" : null)
				.click(function()
				{
					var elem = $(this);
					var articles = listType == "double" ? entries.find("article") : null;
					var table = listType == "single" ? entries.find("table") : null;
					var selected = !elem.hasClass("selected");
					
					elem.toggleClass("selected");
					
					if (selected)
					{
						visible.push(k);
						
						if (table && k == "summary")
							table.find(".summary").removeClass("hidden");
					}
					else
					{
						delete visible[$.inArray(k, visible)];
						
						if (table && k == "summary")
							table.find(".summary").addClass("hidden");
					}
					
					megalopolis.mainCookie("ListVisibility", visible.join(",").replace(/,{2,}/g, ","));
					
					if (k != "summary")
					{
						if (articles)
						{
							articles.find("dd." + k).prev("dt").andSelf().toggleClass("hidden");
							articles.find("dl").each(function(k, v)
							{
								var elems = $("dt", v);
								var firstVisible = elems.not(".hidden").first();
								
								elems.removeClass("firstChild");
								firstVisible.addClass("firstChild");
							});
							articles.find("time ." + k).toggleClass("hidden");
						}
						else if (table)
						{
							var tagRows = table.find("tr.tags").find("td");
							var fromColSpan = tagRows.prop("colspan");
							
							$([table.find("th." + k), table.find("td." + k)]).each(function(k, v)
							{
								var elem = $(this);
								
								if (elem.hasClass("hidden"))
									tagRows.prop("colspan", fromColSpan + 1);
								else
									tagRows.prop("colspan", fromColSpan - 1);
								
								elem.toggleClass("hidden");
							});
						}
					}
					
					return false;
				});
		}));
		
		control.mouseenter(function()
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
		.appendTo(h1);
	};
	var loadAdminButtons = function()
	{
		$("#unpostButton").click(function()
		{
			return window.confirm("本当に作品を削除してよろしいですか？");
		});
	};
	var loadSubjectPager = function()
	{
		var form = $(".pagerContainer.subjectPager form");
		
		form.find("button").remove();
		form.find("select").change(function()
		{
			location.href = form.prop("action") + $(this).val();
		});
	};
	var showSummary = function()
	{
		var tbody = entries.find("tbody");
		var toggle = function(e)
		{
			var row = $(e.target).parents("tr");
			
			(row.hasClass("tags") ? row : row.next("tr.tags")).find("p").toggleClass("hidden");
		};
		
		tbody.find(".summaryButton").click(function(e)
		{
			toggle(e);
			
			return false;
		});
		tbody.click(function(e)
		{
			var tagName = e.target.tagName.toLowerCase();
			
			if (tagName != "a" &&
				tagName != "input")
			{
				toggle(e);
				
				return false;
			}
		});
	};
	
	if (listType == "single")
		showSummary();
	
	loadSubjectPager();
	
	if (entries.length)
	{
		loadSortDropDown();
		loadVisibilityDropDown();
	}
	
	loadAdminButtons();
});
