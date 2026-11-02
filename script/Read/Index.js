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
						$("<div />")
							.addClass("notify info")
							.text("評価を受け付けました！" + (point == 0 ? "無評価" : point + "点"))
							.prepend($("<span />")
								.addClass("buttons")
								.append($("<input />", { type: "button" })
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
												$("<div />")
													.addClass("notify info")
													.text("評価を取り消しました")
													.hide()
													.prependTo(form)
													.slideDown(250);
												
												updatePoints(-data.point);
											}
										});
										
										return false;
									})))
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
						
						$("<div />")
							.addClass("notify info")
							.text("コメントを受け付けました！" + ($("#point").val() == 0 ? "無評価" : $("#point").val() + "点"))
							.prepend($("<span />")
								.addClass("buttons")
								.append($("<input />", { type: "button" })
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
												$("<div />")
													.addClass("notify info")
													.text("コメントを取り消しました")
													.prepend($("<span />")
														.addClass("buttons")
														.append($("<input />", { type: "button" })
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
															})))
													.hide()
													.prependTo(form)
													.slideDown(250);
											}
										});
										
										return false;
									})))
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
			// 全画面では、枠の高さを CSS (.fullscreen) で画面に合わせる
			if (fullscreen && fullscreen.isFullscreen())
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
		});

		if (writingMode != 0)
			directionGroup.append($("<p />").addClass("note").text("この作品は作者が" + (writingMode == 2 ? "縦書き" : "横書き") + "を指定しています。切り替えはこの作品を開いている間だけ有効です"));

		panel
			.append(directionGroup)
			.append($("<div />").addClass("actions").append($("<button />", { type: "button" }).text("初期設定に戻す").click(function()
			{
				settings.fontSize = 100;
				settings.lineHeight = "1.8";
				settings.fontFamily = "sans";
				$.each(["FontSize", "LineHeight", "FontFamily"], function(k, v) { megalopolis.mainCookie(v, null); });
				fontSizeOutput.text("100%");
				panel.find("input[name=readingLineHeight][value='1.8'], input[name=readingFontFamily][value=sans]").prop("checked", true);
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
		 * 本文の枠を全画面に広げる。全画面の間は、このボタンと表示設定も枠の中に移す
		 */
		var fullscreen = (function()
		{
			var doc = document;
			var enabled = doc.fullscreenEnabled || doc.webkitFullscreenEnabled;
			var element = function() { return doc.fullscreenElement || doc.webkitFullscreenElement || null; };
			var isFullscreen = function() { return element() == content[0]; };

			if (!enabled || !content[0].requestFullscreen && !content[0].webkitRequestFullscreen)
				return { isFullscreen: isFullscreen };

			var button = $("<button />", { type: "button", "class": "fullscreenButton" });
			var icon = $("<img />").appendTo(button);
			var progress = null;
			var update = function()
			{
				var on = isFullscreen();
				var label = on ? "全画面表示を終了" : "全画面表示";

				button.attr("title", label);
				icon.attr({ src: basePath + (on ? "fullscreenExitIcon.png" : "fullscreenIcon.png"), alt: label });
			};
			var onChange = function()
			{
				var on = isFullscreen();
				var focused = button.is(":focus");

				content.toggleClass("fullscreen", on);

				// 枠の外に置いたボタンは全画面では見えないため、枠の中へ移す
				if (on)
					options.prependTo(content);
				else
					options.insertBefore(content);

				update();
				resizeAction();

				if (focused)
					button.focus();

				// 読み始める前 (0) なら、題名から見えるよう位置を合わせない
				if (progress)
				{
					readingPosition.restore(progress);
					progress = null;
				}
			};

			button.click(function()
			{
				progress = readingPosition.current();

				if (isFullscreen())
					(doc.exitFullscreen || doc.webkitExitFullscreen).call(doc);
				else
				{
					var request = (content[0].requestFullscreen || content[0].webkitRequestFullscreen).call(content[0]);

					if (request && request.catch)
						request.catch(function() { progress = null; });
				}
			});
			$(doc).on("fullscreenchange webkitfullscreenchange", onChange);
			update();
			$("<li />").append(button).prependTo(options);

			return { isFullscreen: isFullscreen };
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

				// 全画面では枠の中を、それ以外ではページをスクロールする
				if (fullscreen.isFullscreen())
					return clamp((content[0].getBoundingClientRect().top - rect.top) / Math.max(rect.height - content[0].clientHeight, 1));

				return clamp(-rect.top / Math.max(rect.height - $(window).height(), 1));
			};
			var restore = function(progress)
			{
				var rect = $("#content")[0].getBoundingClientRect();

				if (isVerticalNow())
				{
					var frame = wrapper[0].getBoundingClientRect();
					var delta = progress * Math.max(rect.width - frame.width, 0) - Math.max(rect.right - frame.right, 0);

					if (!fullscreen.isFullscreen())
						window.scrollTo(window.pageXOffset, content.offset().top - 8);

					wrapper.scrollLeft(wrapper.scrollLeft() - delta);
				}
				else if (fullscreen.isFullscreen())
					content.scrollTop(content.scrollTop() + rect.top - content[0].getBoundingClientRect().top + progress * Math.max(rect.height - content[0].clientHeight, 0));
				else
					window.scrollTo(window.pageXOffset, rect.top + window.pageYOffset + progress * Math.max(rect.height - $(window).height(), 0));
			};

			if (!entryId || !$("#content").length)
				return { current: current, restore: restore };

			var record = load()[entryId];
			var navigation = window.performance && performance.getEntriesByType ? performance.getEntriesByType("navigation")[0] : null;
			var restored = navigation && (navigation.type == "reload" || navigation.type == "back_forward");
			var timer = null;
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
				var notice = $("<div />")
					.addClass("notify info resume")
					.text(record.r < 0.05
						? "前回は " + record.p + " ページ目を読んでいました"
						: "前回は " + (record.n > 1 ? record.p + " ページ目の " : "") + Math.round(record.r * 100) + "% まで読みました")
					.prepend($("<span />").addClass("buttons")
						.append($("<input />", { type: "button", value: "続きから読む" }).click(function()
						{
							notice.remove();

							if (record.p == page)
								restore(record.r);
							else
								location.href = content.data("pageBase") + record.p + "#resume";
						}))
						.append($("<input />", { type: "button", value: "閉じる" }).click(function()
						{
							notice.remove();
						})))
					.insertBefore(options);
			}

			// 読み込み直後のスクロール (ブラウザによる位置の復元など) では記録しない
			setTimeout(function() { tracking = true; }, 1000);
			$(window).on("scroll", onScroll);
			wrapper.on("scroll", onScroll);
			content.on("scroll", onScroll);
			$(window).on("pagehide", function()
			{
				if (tracking)
					record_();
			});

			return { current: current, restore: restore };
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
	};
	
	loadAdminButtons();
	loadPostButtons();
	loadButtons();
	loadOptions();
});
