$(function()
{
	var list = $(".search section ul.params");
	var texts = $("input[type='text']", list);
	var nums = $("input[type='number'], input[type='date']", list);
	var resize = function()
	{
		texts.each(function()
		{
			var elem = $(this);
			
			elem.width(elem.parent().width() - elem.css("marginLeft").replace(/px$/, "") - 16);
		});
		nums.each(function()
		{
			var elem = $(this);
			
			elem.width(elem.parent().width() / 2 - 80 + 17);
		});
	};
	
	resize();
	$(window).resize(resize);
});