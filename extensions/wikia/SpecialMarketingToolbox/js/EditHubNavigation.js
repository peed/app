var ModuleNavigation = function() {
};

ModuleNavigation.prototype = {
	boxes: undefined,
	switchSelector: 'input:not(:button), textarea, .filename-placeholder, .image-placeholder img',

	init: function () {
		this.boxes = $('#marketing-toolbox-form').find('.module-box');
		this.initButtons();
	},

	initButtons: function() {
		this.boxes.filter(':first').find('.nav-up').attr('disabled', 'disabled');
		this.boxes.filter(':last').find('.nav-down').attr('disabled', 'disabled');

		this.boxes.find('.nav-up').filter(':not(:disabled)').click($.proxy(this.moveUp, this));
		this.boxes.find('.nav-down').filter(':not(:disabled)').click($.proxy(this.moveDown, this));
	},

	moveUp: function(event) {
		event.preventDefault();
		var sourceBox = $(event.target).parents('.module-box');
		var destBox = sourceBox.prev();
		this.switchValues(sourceBox, destBox);
	},

	moveDown: function(event) {
		event.preventDefault();
		var sourceBox = $(event.target).parents('.module-box');
		var destBox = sourceBox.next();
		this.switchValues(sourceBox, destBox);
	},

	switchValues: function(source, dest) {
		var sourceContainers = source.find(this.switchSelector);
		var destContainers = dest.find(this.switchSelector);

		var sourceContainersLength = sourceContainers.length;
		var destContainersLength = destContainers.length;

		if (sourceContainersLength != destContainersLength) {
			throw "Switchable length not equals";
		}
		for (var i = 0; i < sourceContainersLength; i++) {
			this.switchElementValue(sourceContainers[i], destContainers[i]);
		}
		dest.get(0).scrollIntoView();
	},

	switchElementValue: function(source, dest) {
		var sourceTagName = source.nodeName.toLowerCase();
		var tmp;
		if (sourceTagName != dest.nodeName.toLowerCase()) {
			throw "Switchable type not equals";
		}
		var imgAttribsToSwitch = ['src', 'width', 'height'];

		source = $(source);
		dest = $(dest);

		var form = EditHub.form.validate();

		switch(sourceTagName) {
			case 'span':
				tmp = source.text();
				source.text(dest.text());
				dest.text(tmp);
				break;
			case 'img':
				for (var i = 0; i < imgAttribsToSwitch.length; i++) {
					tmp = source.attr(imgAttribsToSwitch[i]);
					source.attr(imgAttribsToSwitch[i], dest.attr(imgAttribsToSwitch[i]));
					dest.attr(imgAttribsToSwitch[i], tmp);
				}
				break;
			case 'textarea':
			default:
				var sourceInvalid = this.checkFieldsValidationErrors(form, source);
				var destInvalid = this.checkFieldsValidationErrors(form, dest);

				tmp = source.val();
				source.val(dest.val());
				dest.val(tmp);


				if (sourceInvalid) {
					this.switchValidationError(form, source, dest);
				}

				if (destInvalid) {
					this.switchValidationError(form, dest, source);
				}

		}
	},
	switchValidationError: function(form, source, dest) {
		var cleanedElement = form.clean(source);
		delete form.invalid[cleanedElement.name];
		form.prepareElement(cleanedElement);
		form.hideErrors();
		form.settings.unhighlight(cleanedElement, form.settings.errorClass, form.settings.validClass);
		form.element(dest);
	},
	checkFieldsValidationErrors: function(form, field) {
		return form.errors().filter('[for="' + form.idOrName(form.clean(field)) + '"]:visible').exists();
	}
};

var moduleNavigation = new ModuleNavigation();
$(function () {
	moduleNavigation.init();
});