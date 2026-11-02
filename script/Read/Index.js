$(function()
{
	var commentForm = $("#commentform");
	var jsonAction = function(action)
	{
		action = action.replace(/#.*$/, "");
		var match = action.match(/([?&]path=)([^&]*)/);

		return match
			? action.replace(match[0], match[1] + match[2] + ".json")
			: action + ".json";
	};
	var queryAction = function(action, query)
	{
		return action + (action.indexOf("?") >= 0 ? "&" : "?") + query;
	};
	/**
	 * お知らせを作る。文言 (.message) を左に、ボタンがあれば右 (.buttons) に置く
	 */
	var notify = function(type, message, buttons)
	{
		return $("<div />")
			.addClass("notify " + type)
			.append($("<span />").addClass("message").text(message))
			.append(buttons ? $("<span />").addClass("buttons").append(buttons) : null);
	};
	var loadPostButtons = function()
	{
		$("#resumeForm, #sendForm").find("button").click(function()
		{
			$(this).attr("disabled", "disabled").parents("form").submit();
			
			return false;
		});
	};
	var loadButtons = function()
	{
		var defaultEvaluator = $("#links").data("defaultEvaluator");
		var use = !commentForm.length ? null : commentForm.data("use").split(" ");
		var showName = use && $.inArray("name", use) >= 0;
		var showPoint = use && $.inArray("points", use) >= 0;
		var defaultName = !commentForm.length ? null : commentForm.data("defaultName");
		
		if (defaultEvaluator != -1)
		{
			var forms = ["evaluateform", "commentform"];
			
			var switchForm = function(id)
			{
				$.each(forms, function(k, v)
				{
					var isActive = id == v;
					
					if (isActive)
					{
						$("#" + v + "Headding").removeClass("inactive");
						$("#" + v).show();
						$("#" + v + ">*").fadeIn();
					}
					else
					{
						$("#" + v + "Headding").addClass("inactive");
						$("#" + v).hide();
						$("#" + v + ">*").hide();
					}
				});
			};
		
			$($.map(forms, function(_) { return "#" + _ + "Headding"; }).join(","))
				.appendTo($("#links"))
				.addClass("tab")
				.click(function(e)
				{
					switchForm(e.target.href.substr(e.target.href.indexOf("#") + 1));
					
					return false;
				});
			switchForm(location.hash.length > 0 && location.hash.match(/Headding$/)
				? location.hash.substring(1, location.hash.length - "Headding".length)
				: forms[defaultEvaluator]);
		};
		var createCommentFromTemplate = function(data)
		{
			var dt = $("<dt />")
				.prop("id", "comment" + data.num)
				.text(data.num + ".");
			
			if (showPoint)
			{
				dt.append($("<span />")
					.addClass("point")
					.addClass(!data.evaluation ? "none" : data.evaluation > 0 ? "plus" : "minus")
					.text(data.evaluation ? data.evaluation : "無評価"));
				
				if (data.evaluation)
					dt.append("点");
			}
			
			if (showName)
			{
				var nameValue = data.name && data.name.length > 0 ? data.name : defaultName;
				
				dt.append($("<span />")
					.addClass("name")
					.append(data.mail
						? $("<a />").prop("href", "mailto:" + data.mail).text(nameValue)
						: nameValue));
			}
			
			dt.append($("<time />")
				.prop("datetime", megalopolis.unixTimeAsString(data.dateTime, 0))
				.text(megalopolis.unixTimeAsString(data.dateTime, 1)));
			dt.append($("<a />")
				.prop("href", data.deleteAction)
				.text("削除"));
			
			// jQuery 3 では jQuery オブジェクトの配列を $() で包めないため、add() でまとめる
			return dt.add($("<dd />").html(data.formattedBody));
		};
		var evaluate = function(point)
		{
			var form = $("#evaluateform");
			var inputs = $("input", form).attr("disabled", true);
			var lastError = form.children(".notify");
			var updatePoints = function(pt)
			{
				var evaluationDt = $("#evaluation");
				var spanPoint = evaluationDt.children("span.point.plus, span.point.minus");
				
				if (spanPoint.length)
				{
					var value = spanPoint.text().replace(/\s+/g, "") - 0 + pt;
					
					if (value == 0)
						evaluationDt
							.empty()
							.append("0. ")
							.append($("<span />")
								.addClass("point none")
								.text("簡易評価なし"));
					else if (value > 0)
						spanPoint
							.removeClass("minus")
							.addClass("plus")
							.text(value);
					else
						spanPoint
							.addClass("minus")
							.removeClass("plus")
							.text(value);
				}
				else
					evaluationDt
						.empty()
						.append("0. ")
						.append($("<span />")
							.addClass("point")
							.addClass(pt > 0 ? "plus" : "minus")
							.text(pt))
						.append("点 ")
						.append($("<span />")
							.addClass("name")
							.text("簡易評価"));
			};
			var callback = function()
			{
				$.ajax
				({
					type: "POST",
					url: jsonAction(form.attr("action")),
					dataType: "json",
					data:
					{
						postPassword: $("#postPassword").val(),
						point: point
					},
					success: function(data)
					{
						notify("info", "評価を受け付けました！" + (point == 0 ? "無評価" : point + "点"),
								$("<input />", { type: "button" })
									.val("取り消す")
									.one("click", function()
									{
										form.children(".notify").slideUp(250, function(_) { $(this).remove(); });
										
										$.ajax
										({
											type: "POST",
										url: queryAction(jsonAction(form.attr("action").replace(/evaluate#.*$/, "unevaluate")), "id=" + data.id),
											dataType: "json",
											data: {},
											success: function()
											{
												notify("info", "評価を取り消しました")
													.hide()
													.prependTo(form)
													.slideDown(250);
												
												updatePoints(-data.point);
											}
										});
										
										return false;
									}))
							.hide()
							.prependTo(form)
							.slideDown(250);
						
						updatePoints(data.point);
					},
					error: function(xhr)
					{
						var data = JSON.parse(xhr.responseText);
						
						(data && data.error && $.isArray(data.error)
							? $("<ul />")
								.addClass("notify warning")
								.append($.map(data.error, function(_) { return $("<li />").text(_)[0]; }))
							: $("<ul />")
								.addClass("notify error")
								.append($("<li />").text(data.error)))
							.hide()
							.prependTo(form)
							.slideDown(250);
					},
					complete: function()
					{
						inputs.attr("disabled", false);
					}
				});
			};
			
			if (lastError.length)
				lastError.slideUp(250, function(_)
				{
					$(this).remove();
					callback();
				});
			else
				callback();
		};
		var comment = function(data)
		{
			var form = commentForm;
			var inputs = form.find("input, textarea, select").attr("disabled", true);
			var lastError = form.children(".notify");
			var callback = function()
			{
				$.ajax
				({
					type: "POST",
					url: jsonAction(form.attr("action")),
					dataType: "json",
					data:
					{
						name: data ? data.name : $("#name").val(),
						mail: data ? data.mail : $("#mail").val(),
						body: data ? data.body : $("textarea", form).val(),
						password: $("#password").val(),
						postPassword: $("#postPassword2").val(),
						point: data ? data.point : $("#point").val()
					},
					success: function(data)
					{	
						if (!data.name || data.name.length == 0)
							data.name = defaultName;
						
						notify("info", "コメントを受け付けました！" + ($("#point").val() == 0 ? "無評価" : $("#point").val() + "点"),
								$("<input />", { type: "button" })
									.val("取り消す")
									.one("click", function()
									{
										form.children(".notify").slideUp(250, function(_) { $(this).remove(); });
										
										$.ajax
										({
											type: "POST",
										url: queryAction(jsonAction(form.attr("action").replace(/comment#.*$/, "uncomment")), "id=" + data.id),
											dataType: "json",
											data:
											{
												password: $("#password").val()
											},
											success: function()
											{
												$("#comment" + data.num + ", #comment" + data.num + "+dd").remove();
												notify("info", "コメントを取り消しました",
														$("<input />", { type: "button" })
															.val("元に戻す")
															.one("click", function()
															{
																comment
																({
																	name: data.name,
																	mail: data.mail,
																	body: data.body,
																	password: $("#password").val(),
																	postPassword: $("#postPassword2").val(),
																	point: data.evaluation ? data.evaluation : 0
																});
															}))
													.hide()
													.prependTo(form)
													.slideDown(250);
											}
										});
										
										return false;
									}))
							.hide()
							.prependTo(form)
							.slideDown(250);
						megalopolis.scrollTo(createCommentFromTemplate(data).appendTo("#comments")[0]);
						
						$("#comments>dt.none").remove();
						$("textarea", form).val("");
						$("#point").val(0);
					},
					error: function(xhr)
					{
						var data = JSON.parse(xhr.responseText);
						
						(data && data.error && $.isArray(data.error)
							? $("<ul />")
								.addClass("notify warning")
								.append($.map(data.error, function(_) { return $("<li />").text(_)[0]; }))
							: $("<ul />")
								.addClass("notify error")
								.append($("<li />").text(data.error)))
							.hide()
							.prependTo(form)
							.slideDown(250);
					},
					complete: function()
					{
						inputs.attr("disabled", false);
					}
				});
			};
			
			megalopolis.mainCookie("Name", $("#name").val());
			megalopolis.mainCookie("Mail", $("#mail").val());
			megalopolis.mainCookie("Password", $("#password").val());
			
			if (lastError.length)
				lastError.slideUp(250, function(_)
				{
					$(this).remove();
					callback();
				});
			else
				callback();
		};
		
		$("#evaluateform input[type='submit']").click(function()
		{
			evaluate($(this).val());
			
			return false;
		});
		commentForm.submit(function()
		{
			comment();
			
			return false;
		});
	};
	var loadAdminButtons = function()
	{
		$("#unevaluateButton").click(function()
		{
			return window.confirm("本当に評価を削除してよろしいですか？");
		});
		$("#uncommentButton").click(function()
		{
			return window.confirm("本当にコメントを削除してよろしいですか？");
		});
	};
	var loadOptions = function()
	{
		var content = $("#body");
		var wrapper = $("#verticalWrapper");
		var basePath = content.data("stylePath");
		var writingMode = content.data("writingMode");
		var vertical =
		{
			isVertical: null,
			originalHtml: null,
			bodyWidth: null,
			resizeAction: null,
		};
		var lineHeights = [{ value: "1.5", label: "狭い" }, { value: "1.8", label: "標準" }, { value: "2.1", label: "広い" }, { value: "2.4", label: "とても広い" }];
		var fontFamilies = [{ value: "sans", label: "ゴシック" }, { value: "serif", label: "明朝" }];
		var settings =
		{
			fontSize: 100,
			lineHeight: "1.8",
			fontFamily: "sans"
		};
		var fontSizeCookie = megalopolis.mainCookie("FontSize");

		if (fontSizeCookie != null && fontSizeCookie.match(/^[0-9]+%$/))
			settings.fontSize = Math.min(Math.max(fontSizeCookie.replace(/%$/, "") - 0, 50), 300);

		if ($.grep(lineHeights, function(i) { return i.value == megalopolis.mainCookie("LineHeight"); }).length)
			settings.lineHeight = megalopolis.mainCookie("LineHeight");

		if ($.grep(fontFamilies, function(i) { return i.value == megalopolis.mainCookie("FontFamily"); }).length)
			settings.fontFamily = megalopolis.mainCookie("FontFamily");

		var applySettings = function()
		{
			content.css("fontSize", settings.fontSize + "%");
			content[0].style.setProperty("--reading-line-height", settings.lineHeight);
			content.toggleClass("serif", settings.fontFamily == "serif");
		};
		var resizeAction = function()
		{
			// リーダーモードでは、枠の大きさを CSS (.reader) で画面に合わせる
			if (reader && reader.isReader())
				content.css("height", "");
			else if (vertical.isVertical)
				content.height(vertical.verticalBodyWidth ? vertical.verticalBodyWidth + 24 : $(window).height() - 256);
			else
				content.height("auto");
		};
		/**
		 * 縦書きで、前後から独立した半角の 1〜2 桁の数字と、半角の感嘆符・疑問符 (1〜2 文字) を縦中横にする
		 */
		var combineUpright = function(root)
		{
			var pattern = /(^|[^0-9A-Za-z.,:\/!?])([0-9]{1,2}|[!?]{1,2})(?![0-9A-Za-z.,:\/!?])/g;
			var walk = function(node)
			{
				if (node.nodeType == 1)
				{
					if (!/^(script|style|textarea|rt|rp|code|pre)$/i.test(node.nodeName) && !$(node).hasClass("tcy"))
						$.each($.makeArray(node.childNodes), function() { walk(this); });
				}
				else if (node.nodeType == 3 && node.nodeValue.match(pattern))
				{
					var text = node.nodeValue;
					var fragment = document.createDocumentFragment();
					var last = 0;

					text.replace(pattern, function(match, before, target, offset)
					{
						var at = offset + before.length;

						fragment.appendChild(document.createTextNode(text.substring(last, at)));
						fragment.appendChild($("<span />").addClass("tcy").text(target)[0]);
						last = at + target.length;

						return match;
					});
					fragment.appendChild(document.createTextNode(text.substring(last)));
					node.parentNode.replaceChild(fragment, node);
				}
			};

			root.each(function() { walk(this); });
		};
		var toggleVertical = function(onLoad, setVertical)
		{
			if (setVertical == undefined)
				setVertical = megalopolis.mainCookie("Vertical") == "yes";
			
			if (!!vertical.isVertical != setVertical)
				if (setVertical)
				{
					if (vertical.verticalBodyWidth == null)
					{
						var children = $("#content").find("*").filter(function() { return this.style.width && this.style.width.indexOf("%") == -1; });
						
						if (children.length > 0)
						{
							var max = 0;
							
							children.each(function()
							{
								max = Math.max(max, $(this).outerWidth());
							});
							
							vertical.verticalBodyWidth = Math.floor(max);
						}
					}
					
					if (!vertical.originalHtml)
						vertical.originalHtml =
						{
							summary: $("#summary").html(),
							contentBody: $("#contentBody").html(),
							afterwordBody: $("#afterwordBody").html()
						};
					
					var set = $("#summary, #contentBody, #afterwordBody")
						.find("*")
						.filter(function() { return $(this).attr("style"); })
						.each(function()
						{
							var elem = $(this);
							var style = elem[0].style;
							
							$.each
							({
								"margin": "",
								"marginRight": style.marginTop || elem.css("marginTop") || "",
								"marginBottom": style.marginRight || elem.css("marginRight") || "",
								"marginLeft": style.marginBottom || elem.css("marginBottom") || "",
								"marginTop": style.marginLeft || elem.css("marginLeft") || "",
								"padding": "",
								"paddingRight": style.paddingTop || elem.css("paddingTop") || "",
								"paddingBottom": style.paddingRight || elem.css("paddingRight") || "",
								"paddingLeft": style.paddingBottom || elem.css("paddingBottom") || "",
								"paddingTop": style.paddingLeft || elem.css("paddingLeft") || "",
								"border": "",
								"borderRight": style.borderTop || elem.css("borderTop") || "",
								"borderBottom": style.borderRight || elem.css("borderRight") || "",
								"borderLeft": style.borderBottom || elem.css("borderBottom") || "",
								"borderTop": style.borderLeft || elem.css("borderLeft") || "",
								"width": style.height || "auto",
								"height": style.width || "auto"
							}, function(k, v)
							{
								style[k] = v;
								elem.css(k, v);
							});
						});
					// 横書きに戻すときは元の HTML に戻すため、縦中横の要素もそのときに消える
					combineUpright($("#summary, #contentBody, #afterwordBody"));
					
					$("#body").addClass("vertical");
					// 縦書きの本文は枠の中で横にスクロールするため、キーボードの矢印でも動かせるようにする
					wrapper.attr({ tabindex: 0, role: "region", "aria-label": "本文" });
				}
				else
				{
					$("#body").removeClass("vertical");

					// リーダーモードでは横書きでも枠の中でスクロールするため、そのままにする
					if (!reader.isReader())
						wrapper.removeAttr("tabindex role aria-label");
					
					if (vertical.originalHtml)
						$.each(vertical.originalHtml, function(k, v)
						{
							if (v)
								$("#" + k).html(v);
						});
				}
			
			vertical.isVertical = setVertical;
			resizeAction();
		};

		/*
		 * 表示設定のパネル
		 */
		var radioGroup = function(name, legend, items, current, change)
		{
			return $("<fieldset />")
				.append($("<legend />").text(legend))
				.append($("<div />").addClass("segmented").append($.map(items, function(i)
				{
					return $("<label />")
						.append($("<input />", { type: "radio", name: name, value: i.value })
							.prop("checked", i.value == current)
							.change(function()
							{
								if (this.checked)
									change(i.value);
							}))
						.append($("<span />").text(i.label))[0];
				})));
		};
		var trigger = $("<button />", { type: "button", "class": "readingSettingsButton", title: "表示設定", "aria-haspopup": "dialog", "aria-expanded": "false", "aria-controls": "readingSettings" })
			.append($("<img />", { src: basePath + "readingIcon.png", alt: "表示設定" }));
		var fontSizeOutput = $("<output />", { "aria-live": "polite" }).text(settings.fontSize + "%");
		var updateFontSize = function(add)
		{
			settings.fontSize = Math.min(Math.max(settings.fontSize + add, 50), 300);
			megalopolis.mainCookie("FontSize", settings.fontSize + "%");
			fontSizeOutput.text(settings.fontSize + "%");
			applySettings();
		};
		var isVerticalNow = function() { return !!vertical.isVertical; };
		var panel = $("<div />", { id: "readingSettings", "class": "readingSettings", role: "dialog", "aria-label": "表示設定" })
			.prop("hidden", true)
			.append($("<fieldset />")
				.append($("<legend />").text("文字の大きさ"))
				.append($("<div />").addClass("stepper")
					.append($("<button />", { type: "button", "aria-label": "文字を小さく" }).text("−").click(function() { updateFontSize(-10); }))
					.append(fontSizeOutput)
					.append($("<button />", { type: "button", "aria-label": "文字を大きく" }).text("＋").click(function() { updateFontSize(10); }))))
			.append(radioGroup("readingLineHeight", "行間", lineHeights, settings.lineHeight, function(v)
			{
				settings.lineHeight = v;
				megalopolis.mainCookie("LineHeight", v == "1.8" ? null : v);
				applySettings();
			}))
			.append(radioGroup("readingFontFamily", "書体", fontFamilies, settings.fontFamily, function(v)
			{
				settings.fontFamily = v;
				megalopolis.mainCookie("FontFamily", v == "sans" ? null : v);
				applySettings();
			}));
		var directionGroup = radioGroup("readingDirection", "文字の方向", [{ value: "horizontal", label: "横書き" }, { value: "vertical", label: "縦書き" }], writingMode == 0 && megalopolis.mainCookie("Vertical") == "yes" || writingMode == 2 ? "vertical" : "horizontal", function(v)
		{
			var setVertical = v == "vertical";

			// 方向が指定された作品では、全作品共通の設定を書き換えない
			if (writingMode == 0)
				megalopolis.mainCookie("Vertical", setVertical ? "yes" : "no");

			var progress = readingPosition.current();

			toggleVertical(false, setVertical);
			readingPosition.restore(progress);
			reader.refresh();
		});

		if (writingMode != 0)
			directionGroup.append($("<p />").addClass("note").text("この作品は作者が" + (writingMode == 2 ? "縦書き" : "横書き") + "を指定しています。切り替えはこの作品を開いている間だけ有効です"));

		var openModes = [{ value: "normal", label: "通常" }, { value: "reader", label: "全画面" }];
		var openModeGroup = radioGroup("readingOpenMode", "作品を開くとき", openModes, megalopolis.mainCookie("OpenMode") == "reader" ? "reader" : "normal", function(v)
		{
			megalopolis.mainCookie("OpenMode", v == "normal" ? null : v);
		});

		panel
			.append(directionGroup)
			.append(openModeGroup)
			.append($("<div />").addClass("actions").append($("<button />", { type: "button" }).text("初期設定に戻す").click(function()
			{
				settings.fontSize = 100;
				settings.lineHeight = "1.8";
				settings.fontFamily = "sans";
				$.each(["FontSize", "LineHeight", "FontFamily", "OpenMode"], function(k, v) { megalopolis.mainCookie(v, null); });
				fontSizeOutput.text("100%");
				panel.find("input[name=readingLineHeight][value='1.8'], input[name=readingFontFamily][value=sans], input[name=readingOpenMode][value=normal]").prop("checked", true);
				applySettings();
			})));

		var setOpen = function(open)
		{
			panel.prop("hidden", !open);
			trigger.attr("aria-expanded", open ? "true" : "false");
		};

		trigger.click(function()
		{
			setOpen(panel.prop("hidden"));

			if (!panel.prop("hidden"))
				panel.find("button, input:checked").first().focus();
		});
		panel.keydown(function(e)
		{
			if (e.key == "Escape")
			{
				setOpen(false);
				trigger.focus();

				return false;
			}
		});
		$(document).on("click", function(e)
		{
			if (!panel.prop("hidden") && !$.contains(options[0], e.target))
				setOpen(false);
		});

		var options = $('<ul class="options" />')
			.append($("<li />").append(trigger).append(panel))
			.insertBefore(content);

		/*
		 * 本文の枠をブラウザの中で画面いっぱいに広げて読む (リーダーモード)
		 * 題名と作者名を上に、読んでいる位置のスライダーとページの移動を下に置き、表示設定のボタンも枠の中に移す
		 */
		var reader = (function()
		{
			var storageKey = "megalopolis.reader";
			var entryId = content.data("entryId");
			var page = content.data("page") - 0;
			var pageCount = content.data("pageCount") - 0;
			var active = false;
			var prefetched = false;
			// ページを移ったことをスクリーンリーダーに伝える (画面には表示しない)
			var status = $("<p />", { "class": "readerStatus", role: "status" });
			var dragging = false;
			var isReader = function() { return active; };
			var button = $("<button />", { type: "button", "class": "readerButton" });
			var icon = $("<img />").appendTo(button);
			var contents = $("link[rel=contents]").attr("href");
			var header = $("<div />").addClass("readerHeader")
				// 作品集の一覧へ戻る。戻った先から作品を開き直したときは、表示設定の「作品を開くとき」に従う
				.append(contents ? $("<a />", { href: contents, "class": "readerBack", title: "作品集に戻る" })
					.append($("<img />", { src: basePath + "readerBackIcon.png", alt: "作品集に戻る" }))
					.click(function() { setStored(null); }) : null)
				.append($("<p />").addClass("readerTitle")
					.append($("<span />").addClass("title").text(content.data("title") || document.title))
					.append(content.data("name") ? $("<span />").addClass("name").text(content.data("name")) : null));
			var footer = null;
			var overscroll = null;
			var slider = null;
			var label = null;
			var scale = 100;
			var describe = function(p, r)
			{
				return (pageCount > 1 ? p + " / " + pageCount + " ページ　" : "") + Math.round(r * 100) + "%";
			};
			var split = function(value)
			{
				var p = Math.min(Math.floor(value / scale) + 1, pageCount);

				return { p: p, r: Math.min(Math.max((value - (p - 1) * scale) / scale, 0), 1) };
			};
			var updateSlider = function()
			{
				if (!slider || dragging)
					return;

				var r = readingPosition.current();

				slider.val(Math.round((page - 1 + r) * scale));
				slider.attr("aria-valuetext", describe(page, r));
				label.text(describe(page, r));
			};
			// この作品を全画面で読んでいるか (作品の ID)、全画面をやめたか (! と作品の ID) を、タブを閉じるまで覚えておく
			// null の場合は忘れ、次に作品を開いたときは表示設定の「作品を開くとき」に従う
			var setStored = function(on)
			{
				try
				{
					if (on === null || !entryId)
						window.sessionStorage.removeItem(storageKey);
					else
						window.sessionStorage.setItem(storageKey, (on ? "" : "!") + entryId);
				}
				catch (e)
				{
				}
			};

			// ページを移っても続けて読めるよう、ページの移動と位置のスライダーは作品のページを表示しているときだけ置く
			if (entryId && pageCount)
			{
				var go = function(p, r)
				{
					setStored(true);
					readingPosition.goTo(p, r);
				};

				slider = $("<input />", { type: "range", min: 0, max: pageCount * scale, step: 1, "aria-label": "読んでいる位置" });
				label = $("<output />", { "aria-hidden": "true" });
				footer = $("<div />").addClass("readerFooter")
					.append(pageCount > 1 ? $("<button />", { type: "button", "class": "previous", title: "前のページ", "aria-label": "前のページ" }).text("前").prop("disabled", page <= 1).click(function() { go(page - 1, 0); }) : null)
					.append(slider)
					.append(label)
					.append(pageCount > 1 ? $("<button />", { type: "button", "class": "next", title: "次のページ", "aria-label": "次のページ" }).text("次").prop("disabled", page >= pageCount).click(function() { go(page + 1, 0); }) : null);

				// ページの区切りに目盛りを付ける
				if (pageCount > 1)
				{
					var ticks = $("<datalist />", { id: "readerPages" });

					for (var i = 1; i < pageCount; i++)
						ticks.append($("<option />", { value: i * scale }));

					footer.append(ticks);
					slider.attr("list", "readerPages");
				}

				slider
					.on("input", function()
					{
						var target = split(slider.val() - 0);

						dragging = true;
						slider.attr("aria-valuetext", describe(target.p, target.r));
						label.text(describe(target.p, target.r));

						// 表示しているページの中ならその場で動かし、ほかのページは指を離したときに移る
						if (target.p == page)
							readingPosition.restore(target.r);
					})
					.on("change", function()
					{
						var target = split(slider.val() - 0);

						dragging = false;

						if (target.p != page)
							go(target.p, target.r);
					});
				wrapper.on("scroll", updateSlider);

				// ページの最後からさらにスクロールすると次のページへ、先頭からさらに戻すと前のページ (の最後) へ移る
				// 最後のページの最後からさらにスクロールすると、全画面を抜けてコメント欄へ移る
				var comments = $("#links:not(:empty), #evaluateformHeadding, #commentformHeadding, #comments");

				if (pageCount > 1 || comments.length)
					overscroll = (function()
					{
						var makeEdge = function(name, label, action)
						{
							return {
								name: name,
								label: label,
								action: action,
								hint: $("<div />", { "class": "readerEdge " + name, "aria-hidden": "true" })
									.append($("<span />").addClass("label"))
									.append($("<span />").addClass("bar").append($("<span />"))),
								ratio: 0
							};
						};
						var toComments = function()
						{
							// 評価やコメントの欄は切り替えで隠れていることがあるため、見えているものを選ぶ
							var target = comments.filter(":visible").first();
							var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

							set(false);

							if (target.length)
							{
								target[0].scrollIntoView({ block: "start", behavior: reduce ? "auto" : "smooth" });

								// 続けてキーボードで操作できるよう、選ばれている評価やコメントの見出しに移る
								var focusable = target.is("a") ? target : target.find("a:not(.inactive)").first();

								if (focusable.length)
									focusable[0].focus({ preventScroll: true });
							}
						};
						var edges = $.grep([
							page < pageCount
								? makeEdge("next", "次のページ", function() { go(page + 1, 0); })
								: comments.length ? makeEdge("next", "コメント欄", toComments) : null,
							page > 1 ? makeEdge("previous", "前のページ", function() { go(page - 1, 1); }) : null
						], function(i) { return i; });
						var byName = {};
						var armed = false;
						var fired = false;
						var lastWheel = 0;
						var decay = null;
						var touch = null;
						var at = function(name)
						{
							var w = wrapper[0];
							// 縦書きでは右端が先頭、左端が終わり。scrollLeft の符号はブラウザで異なるため絶対値で比べる
							var position = vertical.isVertical ? Math.abs(w.scrollLeft) : w.scrollTop;
							var max = vertical.isVertical ? w.scrollWidth - w.clientWidth : w.scrollHeight - w.clientHeight;

							return name == "next" ? position >= max - 2 : position <= 2;
						};
						var setRatio = function(edge, value, touching)
						{
							edge.ratio = Math.min(Math.max(value, 0), 1);
							edge.hint.find(".bar span").css("width", edge.ratio * 100 + "%");
							edge.hint.find(".label").text(fired || edge.ratio >= 1 && !touching
								? edge.label + "へ移ります"
								: edge.ratio >= 1
									? "指を離すと" + edge.label + "へ移ります"
									: "さらにスクロールすると" + edge.label + "へ");
							show(edge);
						};
						var show = function(edge)
						{
							// 次のページへの案内は最後に着いたら出し、前のページへの案内は戻し始めたときだけ出す
							edge.hint.toggleClass("visible", active && (fired || edge.ratio > 0 || edge.name == "next" && at("next")));
						};
						var reset = function()
						{
							if (!fired)
								$.each(edges, function(k, edge) { setRatio(edge, 0); });
						};
						var fire = function(edge)
						{
							fired = true;
							setRatio(edge, 1);
							edge.action();

							// ページを移らずに全画面を抜けた場合は、もう一度全画面にしたときに使えるよう戻しておく
							if (!active)
							{
								fired = false;
								armed = false;
								reset();
							}
						};
						var check = function()
						{
							// 端に着いたときのスクロールの勢いでは移らないよう、いったん止まるまでは数えない
							if (!at("next") && !at("previous"))
								armed = false;

							reset();
						};

						$.each(edges, function(k, edge) { byName[edge.name] = edge; });
						wrapper.on("scroll", check);
						// 端でさらに読み進める (戻す) 操作を数える。amount が 1 に達すると移る
						var push = function(direction, amount, wait)
						{
							var now = new Date().getTime();
							var idle = now - lastWheel;
							var edge = direction > 0 ? byName.next : direction < 0 ? byName.previous : null;

							lastWheel = now;

							if (!edge || !at(edge.name))
							{
								reset();

								return;
							}

							if (!armed)
							{
								armed = idle > 300;

								if (!armed)
									return;
							}

							clearTimeout(decay);
							decay = setTimeout(reset, wait);
							setRatio(edge, edge.ratio + amount);

							if (edge.ratio >= 1)
								fire(edge);
						};

						wrapper.on("wheel", function(e)
						{
							var ev = e.originalEvent;

							if (!active || fired)
								return;

							// 読み進める向きの量 (縦書きでは、縦のホイールと左への横スクロール)
							var forward = vertical.isVertical && Math.abs(ev.deltaX) > Math.abs(ev.deltaY) ? -ev.deltaX : ev.deltaY;

							forward *= ev.deltaMode == 1 ? 40 : ev.deltaMode == 2 ? wrapper.height() : 1;
							push(forward, Math.abs(forward) / 300, 800);
						});
						// キーボードでは、端に着いてから Space や PageDown などを 2 回押すと移る
						$(document).on("keydown", function(e)
						{
							if (!active || fired || e.isDefaultPrevented() || e.altKey || e.ctrlKey || e.metaKey || $(e.target).is("input, textarea, select, button, a, [contenteditable]"))
								return;

							var forwardKeys = vertical.isVertical ? ["PageDown", "ArrowLeft", "ArrowDown"] : ["PageDown", "ArrowDown"];
							var backwardKeys = vertical.isVertical ? ["PageUp", "ArrowRight", "ArrowUp"] : ["PageUp", "ArrowUp"];
							var direction = e.key == " " ? (e.shiftKey ? -1 : 1)
								: $.inArray(e.key, forwardKeys) >= 0 ? 1
								: $.inArray(e.key, backwardKeys) >= 0 ? -1
								: 0;

							if (direction)
								push(direction, 0.5, 1500);
						});
						wrapper.on("touchstart", function(e)
						{
							var t = e.originalEvent.touches[0];

							touch = active && !fired && e.originalEvent.touches.length == 1 ? { x: t.clientX, y: t.clientY, next: at("next"), previous: at("previous") } : null;
						});
						wrapper.on("touchmove", function(e)
						{
							if (!touch)
								return;

							var t = e.originalEvent.touches[0];
							// 読み進める向きに引っ張った量 (横書きでは上へ、縦書きでは右へ)
							var pull = vertical.isVertical ? t.clientX - touch.x : touch.y - t.clientY;

							touch.edge = pull > 0 && touch.next ? byName.next : pull < 0 && touch.previous ? byName.previous : null;
							$.each(edges, function(k, edge)
							{
								setRatio(edge, edge == touch.edge ? Math.abs(pull) / 120 : 0, true);
							});
						});
						wrapper.on("touchend touchcancel", function(e)
						{
							if (touch && touch.edge && e.type == "touchend" && touch.edge.ratio >= 1)
								fire(touch.edge);
							else
								reset();

							touch = null;
						});
						reset();

						return { hint: $($.map(edges, function(edge) { return edge.hint[0]; })), check: check };
					})();
			}

			var update = function()
			{
				var text = active ? "全画面を終了" : "全画面で読む";

				button.attr({ title: text, "aria-pressed": active ? "true" : "false" });
				icon.attr({ src: basePath + (active ? "fullscreenExitIcon.png" : "fullscreenIcon.png"), alt: "全画面で読む" });
			};
			var set = function(on)
			{
				if (on == active)
					return;

				var focused = button.is(":focus");
				var progress = readingPosition.current();

				active = on;
				$("html").toggleClass("readerMode", on);
				content.toggleClass("reader", on);
				setStored(on);

				// 全画面では続けて次のページへ移ることが多いため、次のページを先に読み込んでおく。
				// 移るときの URL (#resume 付き) と同じ URL を指定しないと、読み込んだ結果が使われない
				if (on && entryId && page < pageCount && !prefetched && window.HTMLScriptElement && HTMLScriptElement.supports && HTMLScriptElement.supports("speculationrules"))
				{
					var rules = document.createElement("script");

					prefetched = true;
					rules.type = "speculationrules";
					rules.textContent = JSON.stringify({ prefetch: [{ source: "list", urls: [content.data("pageBase") + (page + 1) + "#resume"] }] });
					document.head.appendChild(rules);
				}

				// 本文をスクロールする領域を、キーボードの矢印でも動かせるようにする
				if (on)
					wrapper.attr({ tabindex: 0, role: "region", "aria-label": "本文" });
				else if (!vertical.isVertical)
					wrapper.removeAttr("tabindex role aria-label");

				// 枠の外に置いたボタンは見えなくなるため、枠の中の見出しへ移す
				if (on)
				{
					header.append(options).prependTo(content);

					if (footer)
						footer.appendTo(content);

					if (overscroll)
						overscroll.hint.appendTo(content);

					status.appendTo(content);
				}
				else
				{
					header.detach();
					options.insertBefore(content);

					if (footer)
						footer.detach();

					if (overscroll)
						overscroll.hint.detach();

					status.text("").detach();
				}

				update();
				resizeAction();

				if (focused)
					button.focus();

				readingPosition.restore(progress);
				updateSlider();

				if (overscroll)
					overscroll.check();
			};

			button.click(function() { set(!active); });
			$(document).on("keydown", function(e)
			{
				if (active && e.key == "Escape" && !$(e.target).closest("details[open]").length && !e.isDefaultPrevented())
					set(false);
			});
			update();
			$("<li />").append(button).prependTo(options);

			return {
				isReader: isReader,
				// 方向を切り替えたあとなどに、位置の表示と次のページへの案内を合わせ直す
				refresh: function()
				{
					updateSlider();

					if (overscroll)
						overscroll.check();
				},
				// リーダーモードのままページを移ったときは、移った先でもリーダーモードで開く
				// 作品を開いたときは、表示設定で全画面を選んでいれば全画面で開く。
				// ただし、この作品で全画面をやめた後や、コメントなどへのリンクから開いたときは開かない
				restoreOnLoad: function()
				{
					try
					{
						var stored = entryId ? window.sessionStorage.getItem(storageKey) : null;
						var direct = !location.hash || location.hash == "#resume" || location.hash == "#body";

						if (entryId && (stored == entryId || stored != "!" + entryId && direct && megalopolis.mainCookie("OpenMode") == "reader"))
						{
							set(true);

							// 続けてキーボードでスクロールできるよう、本文の領域に移る
							wrapper[0].focus({ preventScroll: true });

							// 全画面のままページを移ってきたときは、何ページ目かを伝える
							if (stored == entryId && pageCount > 1)
								setTimeout(function() { status.text(pageCount + " ページ中 " + page + " ページ目"); }, 500);
						}
					}
					catch (e)
					{
					}
				}
			};
		})();

		/*
		 * 作品ごとに読んでいたページと位置を記録し、次に開いたときに続きから読めるようにする
		 */
		var readingPosition = (function()
		{
			var entryId = content.data("entryId");
			var page = content.data("page") - 0;
			var pageCount = content.data("pageCount") - 0;
			var storageKey = "megalopolis.reading:" + content.data("readingScope");
			var maxRecords = 200;
			var load = function()
			{
				try
				{
					return JSON.parse(window.localStorage.getItem(storageKey) || "{}") || {};
				}
				catch (e)
				{
					return {};
				}
			};
			var save = function(record)
			{
				try
				{
					var data = load();
					var ids = [];

					data[entryId] = record;

					for (var i in data)
						ids.push(i);

					// 古い記録から消して、件数を抑える
					ids.sort(function(x, y) { return (data[y].t || 0) - (data[x].t || 0); });
					$.each(ids.slice(maxRecords), function(k, v) { delete data[v]; });
					window.localStorage.setItem(storageKey, JSON.stringify(data));
				}
				catch (e)
				{
				}
			};
			var clamp = function(v) { return Math.min(Math.max(v, 0), 1); };
			// 進み具合は、スクロール量の符号がブラウザで異なる縦書きでも同じに扱えるよう、要素の位置から求める
			var current = function()
			{
				var rect = $("#content")[0].getBoundingClientRect();

				if (isVerticalNow())
				{
					var frame = wrapper[0].getBoundingClientRect();

					// 読み進めると本文は右へ動く
					return clamp((rect.right - frame.right) / Math.max(rect.width - frame.width, 1));
				}

				// リーダーモードでは枠の中 (#verticalWrapper) を、それ以外ではページをスクロールする
				if (reader.isReader())
					return clamp((wrapper[0].getBoundingClientRect().top - rect.top) / Math.max(rect.height - wrapper[0].clientHeight, 1));

				return clamp(-rect.top / Math.max(rect.height - $(window).height(), 1));
			};
			var restore = function(progress)
			{
				var rect = $("#content")[0].getBoundingClientRect();

				// 読み始める前は、本文の前にある題名や概要から見えるよう、枠の先頭に合わせる
				if (progress <= 0)
				{
					if (isVerticalNow())
						wrapper.scrollLeft(0);
					else if (reader.isReader())
						wrapper.scrollTop(0);

					return;
				}

				if (isVerticalNow())
				{
					var frame = wrapper[0].getBoundingClientRect();
					var delta = progress * Math.max(rect.width - frame.width, 0) - Math.max(rect.right - frame.right, 0);

					if (!reader.isReader())
						window.scrollTo(window.pageXOffset, content.offset().top - 8);

					wrapper.scrollLeft(wrapper.scrollLeft() - delta);
				}
				else if (reader.isReader())
					wrapper.scrollTop(wrapper.scrollTop() + rect.top - wrapper[0].getBoundingClientRect().top + progress * Math.max(rect.height - wrapper[0].clientHeight, 0));
				else
					window.scrollTo(window.pageXOffset, rect.top + window.pageYOffset + progress * Math.max(rect.height - $(window).height(), 0));
			};

			var navigating = false;
			// 指定したページの指定した位置へ移る
			var goTo = function(p, r)
			{
				navigating = true;
				clearTimeout(timer);
				save({ p: p, r: Math.round(r * 1000) / 1000, n: pageCount, t: new Date().getTime(), done: false });
				location.href = content.data("pageBase") + p + "#resume";
			};
			var timer = null;

			if (!entryId || !$("#content").length)
				return { current: current, restore: restore, goTo: goTo };

			var record = load()[entryId];
			var navigation = window.performance && performance.getEntriesByType ? performance.getEntriesByType("navigation")[0] : null;
			var restored = navigation && (navigation.type == "reload" || navigation.type == "back_forward");
			var tracking = false;
			var record_ = function()
			{
				var progress = current();

				save({ p: page, r: Math.round(progress * 1000) / 1000, n: pageCount, t: new Date().getTime(), done: page == pageCount && progress > 0.98 });
			};
			var onScroll = function()
			{
				if (!tracking)
					return;

				clearTimeout(timer);
				timer = setTimeout(record_, 500);
			};

			if (location.hash == "#resume" && record && record.p == page)
			{
				history.replaceState(null, "", location.href.replace(/#resume$/, "#body"));

				if (document.readyState == "complete")
					setTimeout(function() { restore(record.r); }, 0);
				else
					$(window).on("load", function() { restore(record.r); });
			}
			else if (record && !record.done && !restored && (record.p != page || record.r > 0.05))
			{
				var notice = notify("info resume", record.r < 0.05
						? "前回は " + record.p + " ページ目を読んでいました"
						: "前回は " + (record.n > 1 ? record.p + " ページ目の " : "") + Math.round(record.r * 100) + "% まで読みました", [
						$("<input />", { type: "button", value: "続きから読む" }).click(function()
						{
							notice.remove();

							if (record.p == page)
								restore(record.r);
							else
								location.href = content.data("pageBase") + record.p + "#resume";
						})[0],
						$("<input />", { type: "button", value: "閉じる" }).click(function()
						{
							notice.remove();
						})[0]
					])
					.insertBefore(options);
			}

			// 読み込み直後のスクロール (ブラウザによる位置の復元など) では記録しない
			setTimeout(function() { tracking = true; }, 1000);
			$(window).on("scroll", onScroll);
			wrapper.on("scroll", onScroll);
			$(window).on("pagehide", function()
			{
				if (tracking && !navigating)
					record_();
			});

			return { current: current, restore: restore, goTo: goTo };
		})();
		
		// 縦書きでは、縦方向のホイールで横にスクロールする (右から左へ読み進めるため、下へ回すと左へ動かす)
		wrapper.on("wheel", function(e)
		{
			var ev = e.originalEvent;

			// トラックパッドなどの横方向の操作は、ブラウザに任せる
			if (vertical.isVertical && Math.abs(ev.deltaY) > Math.abs(ev.deltaX))
			{
				// 行単位 (Firefox) やページ単位の量をピクセルに直す
				var delta = ev.deltaY * (ev.deltaMode == 1 ? 40 : ev.deltaMode == 2 ? wrapper.width() : 1);

				wrapper.stop(true, true).animate
				({
					scrollLeft: wrapper.scrollLeft() - delta
				}, 20, "linear");
				
				return false;
			}
		});
		
		$(window).resize(resizeAction);
		applySettings();
		
		if (writingMode == 0 && megalopolis.mainCookie("Vertical") == "yes" || writingMode == 2)
			toggleVertical(true, writingMode == 2 ? true : undefined);

		reader.restoreOnLoad();
	};
	
	loadAdminButtons();
	loadPostButtons();
	loadButtons();
	loadOptions();
});
