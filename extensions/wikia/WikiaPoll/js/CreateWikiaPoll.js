var CreateWikiaPoll = {

	init: function() {
		$.when(
			mw.loader.use('jquery.ui.sortable')
		).
		done(CreateWikiaPoll.initBindings);
	},

	initBindings: function () {
		$("#CreateWikiaPoll")
			.find("ul").sortable({
				axis: "y",
				handle: ".drag",
				opacity: 0.8,
				stop: CreateWikiaPoll.renumber
			}).end()
			.find(".drag").live("mousedown", function(event) {
				event.preventDefault();
			}).end()
			.find(".trash").live("click", CreateWikiaPoll.remove).end()
			.find(".add-new a").click(CreateWikiaPoll.addNew).end()
			.find(".create").click(CreateWikiaPoll.onSave);
		if ($("#CreateWikiaPoll").closest(".modalWrapper")) {
			// Presented in modal. Do specific modal stuff
			$("#CreateWikiaPoll").find(".cancel").click(function(event) {
				event.preventDefault();
				$(this).closest(".modalWrapper").closeModal();
			});
		}
	},

	addNew: function(event) {
		event.preventDefault();
		$("#CreateWikiaPoll .new-item").clone().removeClass("new-item").appendTo("#CreateWikiaPoll ul");
		CreateWikiaPoll.renumber();
	},

	remove: function() {
		$(this).closest("li").slideUp("fast", function() {
			$(this).remove();
			CreateWikiaPoll.renumber();
		});
	},

	renumber: function() {
		$("#CreateWikiaPoll li:not('.new-item') label").each(function(i) {
			$(this).text("#" + (i + 1));
		});
	},

	showEditor: function(event) {
		// load CSS for editor popup and jQuery UI library (if not loaded yet) via loader function
		$.when(
			mw.loader.use('jquery.ui.sortable'),
			$.getResources([
				$.getSassCommonURL('/extensions/wikia/WikiaPoll/css/CreateWikiaPoll.scss'),
				wgExtensionsPath + '/wikia/WikiaPoll/js/CreateWikiaPoll.js'
			]),
			$.nirvana.sendRequest({
				controller: 'WikiaPoll',
				method: 'SpecialPage',
				format: 'html'
			})
		).done(function(dataFromJQueryUI, dataFromGetResources, dataFromNirvana) {
			var html = dataFromNirvana[0];

			$(html).makeModal({width: 600});
			CreateWikiaPoll.init();

			// editing an existing poll?
			if ($(event.target).hasClass("placeholder-poll")) {
				CreateWikiaPoll.editExisting(event.target);
			}
		});
	},

	editExisting: function(placeholder) {
		var pollData = $(placeholder).getData(),
			index,
			li;

		// add hidden form element for pollId
		$("#CreateWikiaPoll").find("form").append('<input type="hidden" name="pollId" value="' + pollData.pollid + '">');

		// store data in main dom element for use when saving
		$("#CreateWikiaPoll").data(pollData);

		// populate question field
		$("#CreateWikiaPoll").find("input[name='question']").val(pollData.question);

		// remove 3 empty answer fields from the default template
		$("#CreateWikiaPoll li:not('.new-item')").remove();

		// generate answer list elements
		for (index in pollData.answers) {
			li = $("#CreateWikiaPoll .new-item").clone().removeClass("new-item").appendTo("#CreateWikiaPoll ul");
			li.find("input").val(pollData.answers[index]);
		}

		// properly number the answers
		CreateWikiaPoll.renumber();
	},

	onSave: function(event) {
		event.preventDefault();

		if ($("#CreateWikiaPoll").data('pollid')) {
			// editing existing poll
			$.get(wgScript + '?action=ajax&rs=WikiaPollAjax&method=update', $("#CreateWikiaPoll").find("form").serialize(), function(data) {
				if ($("#CreateWikiaPoll").closest(".modalWrapper").exists()) { // in modal
					if (data.success) {
						$("#CreateWikiaPoll").closest(".modalWrapper").closeModal();
					}
				} else { // Special:Poll
					if (data.success) {
						document.location = data.url;
					} else if (data.error) {
						$("#CreateWikiaPoll").find(".errorbox").remove().end().prepend(data.error);
					}
				}
			});
		} else {
			// saving new poll
			$.get(wgScript + '?action=ajax&rs=WikiaPollAjax&method=create', $("#CreateWikiaPoll").find("form").serialize(), function(data) {
				if ($("#CreateWikiaPoll").closest(".modalWrapper").exists()) { // in modal
					if (data.success) {
						RTE.mediaEditor._add("[[" + data.question + "]]");
						$("#CreateWikiaPoll").closest(".modalWrapper").closeModal();
					} else if (data.error) {
						$("#CreateWikiaPoll").find(".errorbox").remove().end().prepend(data.error);
					}
				} else { // Special:Poll
					if (data.success) {
						document.location = data.url;
					} else if (data.error) {
						$("#CreateWikiaPoll").find(".errorbox").remove().end().prepend(data.error);
					}
				}
			});
		}
	}
};

$(function() {
	if (wgAction != "edit" && wgAction != "submit"){
		// only init on special page
		CreateWikiaPoll.init();
	}
});
