$(function()
{
	var commentForm = $("#commentform");
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
			
			return $([dt, $("<dd />").html(data.formattedBody)]);
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
					url: form.attr("action").replace(/#.*$/, "").replace(/(\?.*)|$/, ".json$1"),
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
											url: form.attr("action").replace(/evaluate#.*$/, "unevaluate") + ".json?id=" + data.id,
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
						var data = $.parseJSON(xhr.responseText);
						
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
					url: form.attr("action").replace(/#.*$/, "").replace(/(\?.*)|$/, ".json$1"),
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
											url: form.attr("action").replace(/comment#.*$/, "uncomment") + ".json?id=" + data.id,
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
						var data = $.parseJSON(xhr.responseText);
						
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
			taketori: null,
			forceTaketori: content.data("forceTaketori"),
			isVertical: null,
			originalHtml: null,
			bodyWidth: null,
			resizeAction: null,
		};
		var cookie = megalopolis.mainCookie("FontSize");
		var f = 0;
		var working = false;
		
		if (cookie != null && cookie.match(/^[0-9]+%$/))
		{
			content.css("fontSize", f = cookie);
			
			var val = cookie.replace(/%$/, "") - 0;
			content.css("lineHeight", val + 30 + "%");
		}
		else
		{
			content.css("fontSize", f = "100%");
			content.css("lineHeight", "130%");
		}
		
		var updateFontSize = function(add)
		{
			var val = f.replace(/%$/, "") - 0 + add;
			
			content.css("fontSize", megalopolis.mainCookie("FontSize", f = val + "%"));
			content.css("lineHeight", val + 30 + "%");
			$("<span />")
				.addClass("hint")
				.text(f)
				.prependTo(content)
				.delay(500)
				.fadeOut(500, function() { $(this).remove(); });
		};
		var resizeAction = function()
		{
			if (vertical.isVertical)
				content.height(vertical.verticalBodyWidth ? vertical.verticalBodyWidth + 24 : $(window).height() - 256);
			else
				content.height("auto");
			
			if (!vertical.taketori && window.opera)
				if (vertical.isVertical)
				{
					var ow = Math.floor(content.outerWidth());
					var oh = Math.floor(content.outerHeight());
					
					wrapper.width(oh - 2).height(ow).css("right", -oh + "px");
				}
				else
					wrapper.width("auto").height("auto").css("right", "");
		};
		var toggleVertical = function(onLoad, setVertical)
		{
			if (setVertical == undefined)
				setVertical = megalopolis.mainCookie("Vertical") == "yes";
			
			if (vertical.isVertical != setVertical)
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
					
					if (vertical.taketori)
						vertical.taketori.toggleAll();
					else if (!$.browser.msie && !megalopolis.isWebKit() && (vertical.forceTaketori || $.browser.mozilla || navigator.platform.indexOf("Win") == -1))
					{
						vertical.taketori = new Taketori()
							.set
							({
								fontFamily: "sans-serif",
								height: vertical.verticalBodyWidth ? vertical.verticalBodyWidth + 16 + "px" : ($(window).height() - 256 - 8) + "px"
							})
							.element("#contentWrapper");
						vertical.taketori.ttbDisabled = false;
						vertical.taketori.deleteCookie("TTB_DISABLED");
						vertical.taketori.toVertical(onLoad ? true : false);
					}
					else
					{
						if (!window.opera)
						{
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
							$.each
							({
								summary: $("#summary").html(),
								contentBody: $("#contentBody").html(),
								afterwordBody: $("#afterwordBody").html()
							}, function(k, v)
							{
								if (v)
									$("#" + k).html(v);
							});
						}
						
						$("#body").addClass("vertical");
					}
				}
				else if (vertical.taketori)
					vertical.taketori.toggleAll();
				else
				{
					$("#body").removeClass("vertical");
					
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
		
		$('<ul class="options" />')
			.append($.map
			([
				{
					title: "文字を拡大",
					image: "plusFontIcon.png",
					click: function()
					{
						updateFontSize(10);
						
						return false;
					}
				},
				{
					title: "文字を縮小",
					image: "minusFontIcon.png",
					click: function()
					{
						updateFontSize(-10);
						
						return false;
					}
				},
				{
					title: "文字方向の切り替え",
					image: writingMode == 0 && megalopolis.mainCookie("Vertical") == "yes" || writingMode == 2 ? "horizontalIcon.png" : "verticalIcon.png",
					separator: true,
					click: function(sender)
					{
						if (vertical.taketori && $("#contentWrapper").hasClass("taketori-in-progress"))
							return;
						
						var rt = megalopolis.mainCookie("Vertical", vertical.isVertical ? "no" : "yes");
						
						toggleVertical();
						
						$("img", sender).attr("src", basePath + (rt == "yes" ? "horizontalIcon.png" : "verticalIcon.png"));
					}
				}
			], function(i)
			{
				return $("<li />")
					.addClass(i.separator ? "split" : "")
					.append($("<a />",
					{
						href: "#"
					})
						.append($("<img />",
						{
							src: basePath + i.image,
							alt: i.title,
							title: i.title
						}))
					.click(function()
					{
						if (!working)
						{
							working = true;
							i.click($(this));
							working = false;
						}
						
						return false;
					}))[0];
			}))
			.insertBefore(content);
		
		if (megalopolis.isWebKit())
			wrapper.on("mousewheel", function(e)
			{
				if (vertical.isVertical)
				{
					wrapper.stop(true, true).animate
					({
						scrollLeft: wrapper.scrollLeft() + e.originalEvent.wheelDelta,
					}, 20, "linear");
					
					return false;
				}
			});
		
		$(window).resize(resizeAction);
		
		if (writingMode == 0 && megalopolis.mainCookie("Vertical") == "yes" || writingMode == 2)
			toggleVertical(true, writingMode == 2 ? true : undefined);
	};
	
	loadAdminButtons();
	loadPostButtons();
	loadButtons();
	loadOptions();
});