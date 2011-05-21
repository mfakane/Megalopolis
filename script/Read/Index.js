megalopolis.read =
{
	taketori: null,
	evaluate: function(point)
	{
		var form = $("#evaluateform");
		var inputs = $("input", form).attr("disabled", "disabled");
		var lastError = form.children(".notify");
		
		function updatePoints(pt)
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
		}
		
		function callback()
		{
			$.ajax
			({
				type: "POST",
				url: form.attr("action").replace(/#.*$/, "") + ".json",
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
						: $("<p />")
							.addClass("notify error")
							.text("不明なエラーが発生しました"))
						.hide()
						.prependTo(form)
						.slideDown(250);
				},
				complete: function()
				{
					inputs.attr("disabled", "");
				}
			});
		}
		
		if (lastError.length)
			lastError.slideUp(250, function(_)
			{
				$(this).remove();
				callback();
			});
		else
			callback();
	},
	comment: function(data)
	{
		var form = $("#commentform");
		var inputs = $("input, textarea, select", form).attr("disabled", "disabled");
		var lastError = form.children(".notify");
		
		function callback()
		{
			$.ajax
			({
				type: "POST",
				url: form.attr("action").replace(/#.*$/, "") + ".json",
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
															megalopolis.read.comment
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
					megalopolis.scrollTo($("#commentTemplate")
						.tmpl(data)
						.appendTo("#comments"));
					
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
						: $("<p />")
							.addClass("notify error")
							.text("不明なエラーが発生しました"))
						.hide()
						.prependTo(form)
						.slideDown(250);
				},
				complete: function()
				{
					inputs.attr("disabled", "");
				}
			});
		}
		
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
	},
	loadOptions: function(basePath)
	{
		var content = $("#body");
		var cookie = megalopolis.mainCookie("fontSize");
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
		
		function updateFontSize(add)
		{
			var val = f.replace(/%$/, "") - 0 + add;
			
			content.css("fontSize", megalopolis.mainCookie("fontSize", f = val + "%"));
			content.css("lineHeight", val + 30 + "%");
			$("<span />")
				.addClass("hint")
				.text(f)
				.prependTo(content)
				.delay(500)
				.fadeOut(500, function() { $(this).remove(); });
		}
		
		$("<ul />")
			.addClass("options")
			.css("backgroundColor", content.css("backgroundColor"))
			.css("backgroundImage", content.css("backgroundImage"))
			.append($.map
			([
				{
					title: "文字を拡大",
					image: "plusFontIcon.png",
					click: function()
					{
						updateFontSize(20);
					}
				},
				{
					title: "文字を縮小",
					image: "minusFontIcon.png",
					click: function()
					{
						updateFontSize(-20);
					}
				},
				{
					title: "文字方向の切り替え",
					image: megalopolis.mainCookie("vertical") == "yes" ? "horizontalIcon.png" : "verticalIcon.png",
					separator: true,
					click: function(sender)
					{
						var rt = megalopolis.mainCookie("vertical", megalopolis.mainCookie("vertical") == "yes" ? "no" : "yes");
						
						megalopolis.read.toggleVertical();
						$("img", sender).attr("src", basePath + "style/" + (rt == "yes" ? "horizontalIcon.png" : "verticalIcon.png"));
					}
				}
			], function(i)
			{
				return $("<li />")
					.addClass(i.separator ? "split" : "")
					.append($("<a />",
					{
						href: "javascript:void(0)"
					})
						.append($("<img />",
						{
							src: basePath + "style/" + i.image,
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
		
		if (megalopolis.mainCookie("vertical") == "yes")
			megalopolis.read.toggleVertical(true);
	},
	loadForms: function()
	{
		var forms = ["evaluateform", "commentform"];
		
		function switchForm(id)
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
		}
	
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
			: "evaluateform");
	},
	toggleVertical: function(onLoad)
	{
		if (megalopolis.mainCookie("vertical") == "yes")
			if (this.taketori)
				this.taketori.toggleAll();
			else if (!$.browser.msie && ($.browser.mozilla || navigator.platform.indexOf("Win") == -1))
				this.taketori = new Taketori()
					.set
					({
						fontFamily: "sans-serif",
						height: "520px"
					})
					.element("#contentWrapper")
					.toVertical(onLoad ? true : false);
			else
				$("#body").addClass("vertical");
		else if (this.taketori)
			this.taketori.toggleAll();
		else
			$("#body").removeClass("vertical");
	}
};

$(function()
{
	$("#evaluateform input[type='submit']").click(function()
	{
		megalopolis.read.evaluate($(this).val());
		
		return false;
	});
	$("#commentform").submit(function()
	{
		megalopolis.read.comment();
		
		return false;
	});
	$("#unevaluateButton").click(function()
	{
		return window.confirm("本当に評価を削除してよろしいですか？");
	});
	$("#uncommentButton").click(function()
	{
		return window.confirm("本当にコメントを削除してよろしいですか？");
	});
});