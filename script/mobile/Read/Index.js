megalopolis.read =
{
	pageWidth: 0,
	pageHeight: 0,
	lastMouseUp: 0,
	pages: [],
	baseHtml: null,
	shiftPage: function(self, move)
	{
		var left = self.offset().left + move;
		var oldLeft = left;
		var offsets = $.map(this.pages, function(_) { return -_.position().left; });
		
		if (left < offsets[0])
			left = offsets[0];
		else if (left > offsets[offsets.length - 1])
			left = offsets[offsets.length - 1];
		else
			left = $.grep(offsets, function(_) { return left - megalopolis.read.pageWidth / 2 < _; })[0];
		
		$(".currentPage:last").text((offsets.indexOf(left) + 1) + "/" + offsets.length);
		self.stop().animate({ left: left }, 250);
		
		return left != oldLeft;
	},
	renderPage: function()
	{
		this.lastMouseUp = 0;
		this.pages = [];
		this.pageWidth = window.innerWidth;
		this.pageHeight = Math.max(window.innerHeight, megalopolis.minHeight);
		
		function onMouseDown(elem, e)
		{
			elem.data("down", true)
				.data("x", e.clientX)
				.data("left", elem.position().left)
				.data("mouse", e.pageX);
		}
		
		function onMouseUp(elem, e)
		{
			if (self.shiftPage(elem.data("down", false), Math.abs(e.pageX - elem.data("mouse")) > 32
				? e.pageX > elem.data("mouse")
					? self.pageWidth / 2
					: -self.pageWidth / 2
				: 0))
				self.lastMouseUp = $.now();
		}
		
		function onMouseMove(elem, e)
		{
			if (elem.data("down"))
				elem.offset({ left: elem.data("left") - elem.data("x") + (e.clientX) });
		}
		
		var self = this;
		var y = 0;
		var wrapper = $(".contentWrapper:last")
			.width(this.pageWidth)
			.height(this.pageHeight)
			.mousedown(function(e) { onMouseDown($(this), e); })
			.mouseup(function(e) { onMouseUp($(this), e); })
			.mousemove(function(e) { onMouseMove($(this), e); })
			.click(function(e)
			{
				return $.now() - self.lastMouseUp > 250;
			});
		
		if ($.support.touch)
			wrapper
				.bind("swipeleft", function() { self.shiftPage(wrapper, -self.pageWidth); })
				.bind("swiperight", function() { self.shiftPage(wrapper, self.pageWidth); });
		var i = 0;
		var pp = new Nehan.PageProvider
		({
			direction: "vertical",
			width: this.pageWidth - 24,
			height: this.pageHeight - 72,
			fontSize: 14
		}, this.baseHtml ? this.baseHtml : this.baseHtml = wrapper.html());
		
		pp.setElementHandler("h1", function(proxy, tag) { proxy.startFont({ scale: 1.25, weight: "bold" }); });
		pp.setElementHandler("/h1", function(proxy, tag) { proxy.endFont(); });
		pp.setElementHandler("h2", function(proxy, tag) { proxy.pushLine(); proxy.startFont({ scale: 1.2, weight: "bold" }); });
		pp.setElementHandler("/h2", function(proxy, tag) { proxy.endFont(); proxy.pushLine(); });
		pp.setElementHandler("address", function(proxy, tag) {proxy.pushWord("&nbsp;"); });
		pp.setElementHandler("/address", function(proxy, tag) { proxy.pushLine(); });
		pp.setElementHandler("/div", function(proxy, tag) { proxy.pushLine(); });
		pp.setElementHandler("/p", function(proxy, tag) { proxy.pushLine(); });
		pp.setElementHandler("footer", function(proxy, tag) { proxy.startFont({ scale: 0.95, color: "#333333" }); });
		pp.setElementHandler("/footer", function(proxy, tag) { proxy.endFont(); });
		pp.setElementHandler("br", function(proxy, tag) { proxy.pushLine(); });
		wrapper.empty();
		
		while (pp.hasNextPage())
		{
			var p = pp.outputPage(i++);
			var elem = $("<div />")
				.addClass("page")
				.width(this.pageWidth)
				.html(p.html)
				.prepend($("<span />")
					.addClass("pageNumber")
					.text(i))
				.appendTo(wrapper);
			
			this.pages.push(elem);
			
			if (i > 1)
				elem.prepend($("<span />")
					.addClass("title")
					.text($(".read[id='rhome']:last>header>h1").text()));
		}
		
		var width = this.pageWidth * this.pages.length + 16;
		
		wrapper
			.width(width)
			.offset({ left: -width + this.pageWidth });
			
		$(".nextPageButton:last").click(function() { self.shiftPage(wrapper, self.pageWidth); });
		$(".previousPageButton:last").click(function() { self.shiftPage(wrapper, -self.pageWidth); });
		$(".currentPage:last").text("1/" + this.pages.length);
		$(document).one("orientationchange", function() { self.renderPage(); });
	},
	adjustTextBox: function(href)
	{
		var rpost = $(".read[id='rpost']:last");
		
		$("label", rpost).each(function(k, v) { v = $(v); v.next().css("paddingLeft", v.width() + 16); });
		$(".ui-select .ui-btn", rpost).each(function (k, v) { v = $(v); v.width(v.parent().width()) });
		
		rpost.submit(function()
		{
			$.ajax
			({
				type: "POST",
				url: rpost.attr("action") + ".json",
				dataType: "json",
				data:
				{
					name: $("input[name='name']", rpost).val(),
					mail: $("input[name='mail']", rpost).val(),
					body: $("textarea", rpost).val(),
					password: $("input[name='password']", rpost).val(),
					postPassword: $("input[name='postPassword']", rpost).val(),
					point: $("select[name='point']", rpost).val()
				},
				success: function(data)
				{
					history.back();
				},
				error: function(xhr)
				{
					var data = $.parseJSON(xhr.responseText);
					
					alert(data && data.error && $.isArray(data.error)
						? data.error.join(" ")
						: data.error);
				}
			});
			
			return false;
		});
	}
};