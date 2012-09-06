megalopolis.convert =
{
	action: "",
	list: [],
	first: 0,
	minimumBuffer: 10,
	submit: function()
	{
		var allowOverwrite = $("#allowOverwrite").is(":checked");
		var whenNoConvertLineBreakFieldOnly = $("#whenNoConvertLineBreakFieldOnly").is(":checked");
		
		$("#form").remove();
		
		var div = $("<div />")
			.append($("<p />")
				.text("現在変換処理中です。ページを移動しないでください..."))
			.appendTo($("#convert"));
		var bar = $("<div />")
			.width("0%")
			.appendTo($("<div />")
				.addClass("progress pending")
				.prependTo(div));
		
		$.ajax
		({
			type: "GET",
			url: megalopolis.convert.action,
			dataType: "json",
			data:
			{
				p: "list",
				allowOverwrite: allowOverwrite ? "yes" : "no",
				whenNoConvertLineBreakFieldOnly: whenNoConvertLineBreakFieldOnly ? "yes" : "no"
			},
			success: function(data)
			{
				megalopolis.convert.list = data.remaining;
				megalopolis.convert.process(bar, data);
			},
			error: function(xhr)
			{
				var data = xhr.responseText;
				
				$("#convert>div>*").remove();
				$("#convert>div")
					.append($("<p />")
						.text("不明なエラーが発生しました。")
						.append($("<br />"))
						.append($("<br />"))
						.append($("<a />")
							.attr("href", "convert")
							.text("戻る")))
					.append($("<p />")
						.html(data));
			}
		});
		
		return false;
	},
	process: function(bar, obj)
	{
		$.ajax
		({
			type: "GET",
			url: megalopolis.convert.action,
			dataType: "json",
			data:
			{
				p: obj.remaining.join(","),
				c: obj.count,
				b: obj.buffer,
				allowOverwrite: obj.allowOverwrite,
				whenNoConvertLineBreakFieldOnly: obj.whenNoConvertLineBreakFieldOnly
			},
			success: function(data)
			{
				if (data.remaining)
				{
					var remainingBase = megalopolis.convert.first == 0 ? (megalopolis.convert.first = data.first) : data.first;
					var remainingFirst = data.remaining[0].split("-");
					var remainingLast = megalopolis.convert.list[megalopolis.convert.list.length - 2].split("-");
					var value = (remainingFirst[1] - remainingBase) / (remainingLast[2] - remainingBase) * 100;
					
					if (value < 99)
					{
						var container = bar.parent();
						var w = bar.width() / container.innerWidth() * 100;
						
						if (value > 0 && container.hasClass("pending"))
							container.removeClass("pending");
						
						if (value > w)
							bar.animate({ width: value + "%" }, 1000);
					}
					
					megalopolis.convert.process(bar, data);
				}
				else
				{
					bar.animate({ width: "100%" }, 500, function()
					{
						bar.parent().removeClass("pending").addClass("complete");
					});
					$("#convert>div>p").remove();
					$("#convert>div")
						.append($("<p />")
							.text(data.count + " 作品の変換処理が完了しました。")
							.append($("<br />"))
							.append($("<br />"))
							.append($("<a />")
								.attr("href", "../")
								.text("ホームへ戻る")));
				}
			},
			error: function(xhr)
			{
				var data = xhr.responseText;
				
				if (data.indexOf("Maximum execution time") != -1 && data.indexOf("exceeded") != -1)
					if (obj.buffer / 2 > megalopolis.convert.minimumBuffer)
					{
						obj.buffer /= 2;
						megalopolis.convert.process(bar, obj);
						
						return;
					}
				
				$("#convert>div>*").remove();
				$("#convert>div")
					.append($("<p />")
						.text("不明なエラーが発生しました。")
						.append($("<br />"))
						.append($("<br />"))
						.append($("<a />")
							.attr("href", "convert")
							.text("戻る")))
					.append($("<p />")
						.html(data));
			}
		});
	}
};