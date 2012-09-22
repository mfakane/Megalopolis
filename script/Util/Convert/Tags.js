$(function()
{
	var form = $("#form");
	
	if (form.length)
	{
		var action = form.attr("action") + ".json";
		var minimumBuffer = 5;
		var process = function(bar, status, obj)
		{
			$.ajax
			({
				type: "GET",
				url: action,
				dataType: "json",
				data:
				{
					s: obj.next,
					o: obj.nextOffset,
					c: obj.count,
					m: obj.max,
					b: obj.buffer
				},
				success: function(data)
				{
					if (data.next)
					{
						var value = data.current == 0 ? 0 :  ((data.current - 1) / data.max + (data.allChildren > 0 ? (data.allChildren - data.remainingChildren) / data.allChildren * (1.0 / data.max) : 0)) * 100;
						
						if (value < 99)
						{
							var container = bar.parent();
							var w = bar.width() / container.innerWidth() * 100;
							
							if (value > 0 && container.hasClass("pending"))
								container.removeClass("pending");
							
							if (value > w)
							{
								bar.stop(true, true);
								bar.animate({ width: value + "%" }, 1000);
							}
						}
						
						$("*", status).remove();
						status
							.append($("<span />").text("処理済みの作品集: " + (data.current - 1) + "/" + data.max))
							.append($("<br />"))
							.append($("<span />").text("処理済みの作品集中の作品数: " + (data.allChildren - data.remainingChildren) + "/" + data.allChildren));
						process(bar, status, data);
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
									.attr("href", "../../")
									.text("ホームへ戻る")));
					}
				},
				error: function(xhr)
				{
					var data = xhr.responseText;
					
					if (data.indexOf("Maximum execution time") != -1 && data.indexOf("exceeded") != -1)
						if (obj.buffer / 2 > minimumBuffer)
						{
							obj.buffer /= 2;
							process(bar, status, obj);
							
							return;
						}
					
					$("#convert>div>*").remove();
					$("#convert>div")
						.append($("<p />")
							.text("不明なエラーが発生しました。")
							.append($("<br />"))
							.append($("<br />"))
							.append($("<a />")
								.attr("href", "tags")
								.text("戻る")))
						.append($("<p />")
							.html(data));
				}
			});
		};
		
		form.submit(function()
		{
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
			var status = $("<p />")
				.appendTo(div);
			
			$.ajax
			({
				type: "GET",
				url: action,
				dataType: "json",
				data:
				{
					p: "list"
				},
				success: function(data)
				{
					process(bar, status, data);
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
								.attr("href", "tags")
								.text("戻る")))
						.append($("<p />")
							.html(data));
				}
			});
			
			return false;
		});
	}
});