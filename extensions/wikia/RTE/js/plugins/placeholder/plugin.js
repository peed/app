CKEDITOR.plugins.add('rte-placeholder',
{
	previews: false,

	init: function(editor) {
		var self = this;

		editor.on('instanceReady', function() {
			// add node in which template previews will be stored
			self.previews = $('<div>', {id: 'RTEPlaceholderPreviews'}).appendTo(RTE.overlayNode);
		});

		editor.on('wysiwygModeReady', function() {
			// clean previews up
			if (typeof self.previews == 'object') {
				self.previews.html('');
			}

			// get all placeholders (template / magic words / double underscores / broken image links)
			var placeholders = RTE.tools.getPlaceholders();

			// regenerate preview and template info
			placeholders.removeData('preview').removeData('info');

			self.setupPlaceholder(placeholders);
		});
	},

	// get preview HTML and infomration for given placeholder
	getPreview: function(placeholder) {
		var deferred = new $.Deferred(),
			self = this;

		if (!this.previews) {
			// we're not ready yet
			return;
		}

		var chevron = '<img class="chevron chevronBackground" src="'+wgBlankImgUrl+'" />'
			+'<img class="chevron" src="'+wgBlankImgUrl+'" />';

		// get node in which preview is / will be stored
		var preview = placeholder.data('preview');

		// generate node where preview will be stored
		if (typeof preview == 'undefined') {
			// create preview node
			preview = $('<div>').addClass('RTEPlaceholderPreview RTEPlaceholderPreviewLoading');
			preview.html(chevron + '<div class="RTEPlaceholderPreviewInner">&nbsp;</div>');

			// setup events
			preview.bind('mouseover', function() {
				// don't hide this preview
				self.showPreview(placeholder);
			});

			preview.bind('mouseout', function() {
				// hide this preview
				self.hidePreview(placeholder);
			});

			// add it and store it in placeholder data
			this.previews.append(preview);
			placeholder.data('preview', preview);

			// generate preview and get template information
			var data = placeholder.getData();

			// callback for RTE.tools.resolveDoubleBrackets()
			// (will be called directly for elements not wrapped inside double brackets)
			var renderPreview = function(info) {
				// different look for different types
				var title, intro;
				var className = 'RTEPlaceholderPreviewOther';
				var preformattedCode = true;
				var isEditable = false;

				// encode HTML inside wikisyntax
				var code = data.wikitext.replace(/</g, '&lt;').replace(/>/g, '&gt;');

				// messages
				var lang = RTE.getInstance().lang.hoverPreview;

				switch(info.type) {
					case 'tpl':
						className = 'RTEPlaceholderPreviewTemplate';

						title = info.title.replace(/_/g, ' ').replace(/^Template:/, window.RTEMessages.template + ':');
						intro = info.exists ? lang.template.intro : lang.template.notExisting;

						// show wikitext, if template does not exist
						code = info.exists ? info.html : data.wikitext;
						preformattedCode = !info.exists;

						// is this template editable?
						isEditable = (typeof info.availableParams != 'undefined' && info.availableParams.length);
						break;

					case 'comment':
						title = lang.comment.title;
						intro = lang.comment.intro;

						// exclude comment beginning and end markers
						code = data.wikitext.replace(/^<!--\s+/, '').replace(/\s+-->$/, '');

						isEditable = true;
						break;

					case 'broken-image':
						className = 'RTEPlaceholderPreviewBrokenImage';

						// image name (just image name - no parameteres and no brackets)
						var imageName = data.wikitext.replace(/^\[\[/, '').replace(/\]\]$/, '').split('|').shift();
						title = imageName;

						intro = lang.media.notExisting;
						break;

					default:
						title = lang.codedElement.title;
						intro = lang.codedElement.intro;
						break;
				}

				// cut text in position 1 (heading) after 40 characters, followed by an ellipsis
				if (title.length > 40) {
					title = title.substr(0, 40) + RTEMessages.ellipsis;
				}

				// for IE replace \n with <br />
				if (preformattedCode && CKEDITOR.env.ie) {
					code = code.replace(/\n/g, '<br />');
				}

				// placeholder intro
				var intro = '<div class="RTEPlaceholderPreviewIntro">' + intro + '</div>';

				// render [edit] / [delete] buttons
				var tools = '',
					showEdit = true;

				if (showEdit && isEditable) {
					tools += '<img class="sprite edit" src="'+wgBlankImgUrl+'" />' +
						'<a class="RTEPlaceholderPreviewToolsEdit">' + lang.edit + '</a>';
				}

				tools += '<img class="sprite remove" src="'+wgBlankImgUrl+'" />' +
					'<a class="RTEPlaceholderPreviewToolsDelete">' +
					lang['delete'] + '</a>';

				//
				// render HTML
				//
				var html = chevron;

				// preview "header"
				html += '<div class="RTEPlaceholderPreviewInner ' + className + '">';
				html += '<div class="RTEPlaceholderPreviewTitleBar color1"><span />' + title + '</div>';

				// second line
				html += intro;

				// preview content
				html += '<div class="RTEPlaceholderPreviewCode ' +
					(preformattedCode ? 'RTEPlaceholderPreviewPreformatted ' : '') +
					'reset">' + code + '</div>';

				// [edit] / [delete]
				html += '<div class="RTEPlaceholderPreviewTools neutral">' + tools + '</div>';

				html += '</div>';

				// set HTML and type attribute
				preview.removeClass('RTEPlaceholderPreviewLoading').html(html).attr('type', info.type);

				// handle clicks on [delete] button
				preview.find('.RTEPlaceholderPreviewToolsDelete').bind('click', function(ev) {
					RTE.track('visualMode', self.getTrackingType($(placeholder)), 'hover', 'delete');

					RTE.tools.confirm(title, lang.confirmDelete, function() {
						RTE.tools.removeElement(placeholder);

						// remove preview
						preview.remove();
					});
				});

				// handle clicks on [edit] button
				if (showEdit && isEditable) {
					preview.find('.RTEPlaceholderPreviewToolsEdit').bind('click', function(ev) {
						// hide preview
						preview.hide();

						// tracking code
						RTE.track('visualMode', self.getTrackingType($(placeholder)), 'hover', 'edit');

						// call editor for this type of placeholder
						$(placeholder).trigger('edit');
					});
				}

				// make links in preview unclickable
				preview.find('.RTEPlaceholderPreviewCode').bind('click', function(ev) {
					ev.preventDefault();
				});

				// close button
				preview.find('.RTEPlaceholderPreviewTitleBar').children('span').bind('click', function(ev) {
					RTE.track('visualMode', self.getTrackingType($(placeholder)), 'hover', 'close');

					self.hidePreview(placeholder, true);
				});

				// store template / magic word data in placeholder data
				if (data.type == 'double-brackets') {
					placeholder.data('info', info);
				}

				// Resolve promise
				deferred.resolve(preview);
			};

			// get info from backend (only for templates and magic words)
			switch(data.type) {
				case 'double-brackets':
					RTE.tools.resolveDoubleBrackets(data.wikitext, renderPreview);
					break;

				default:
					renderPreview({type: data.type});
			}
		} else {
			deferred.resolve(preview);
		}

		return deferred.promise();
	},

	// show preview popup
	showPreview: function(placeholder) {
		var self = this;

		// clear timeout used to hide preview with small delay
		var timeoutId = placeholder.data('hideTimeout');
		if (timeoutId) {
			clearTimeout(timeoutId);
		}

		$.when(this.getPreview(placeholder)).done(function(preview) {
			// hover preview popup delay: 150ms
			// mouse cursor must remain over placeholder for the duration
			// of the delay for preview to become visible.
			placeholder.data('showTimeout', setTimeout(function() {
				preview.fadeIn();

				// try to remove scrollbars (RT #34048)
				self.expandPlaceholder(preview, placeholder);

				if (preview.is(':visible')) {
					placeholder.trigger('hover');
				}

				var top,
					placeholderHeight = placeholder.height(),
					position = RTE.tools.getPlaceholderPosition(placeholder),
					previewHeight = preview.height(),
					freeBottomSpace = $(RTE.getInstance().getThemeSpace('contents').$).height() - position.top;

				if (freeBottomSpace <= previewHeight) {
					top = position.top - previewHeight - (placeholderHeight / 2);
					preview.addClass('bottom');

				} else {
					top = position.top + (placeholderHeight / 2);
					preview.removeClass('bottom');
				}

				preview.css({
					left: (position.left + (placeholder.width() / 4)) + 'px',
					top: top + 'px'
				});

				// hide remaining previews
				self.previews.children().not(preview).hide();
			}, 150));
		});
	},

	// hide preview popup
	hidePreview: function(placeholder, hideNow) {
		$.when(this.getPreview(placeholder)).done(function(preview) {
			var showTimeout = placeholder.data('showTimeout')

			// clear show timeout
			if (showTimeout) {
				clearTimeout(showTimeout);
			}

			if (hideNow) {
				// hide preview now - "close" has been clicked
				preview.hide();
			}
			else {
				// hide preview 1 sec after mouse is out
				placeholder.data('hideTimeout', setTimeout(function() {
					preview.fadeOut();

					placeholder.removeData('hideTimeout');
				}, 1000));
			}
		});
	},

	// setup given placeholder
	setupPlaceholder: function(placeholder) {
		var self = this;

		// ignore image / video placeholders
		placeholder = placeholder.not('.placeholder-image-placeholder').not('.placeholder-video-placeholder');

		// no placeholders to setup - leave
		if (!placeholder.exists()) {
			return;
		}

		// unbind previous events
		placeholder.unbind('.placeholder');

		placeholder.bind('mouseover.placeholder', function() {
			self.showPreview($(this));
		});

		placeholder.bind('mouseout.placeholder', function() {
			self.hidePreview($(this));
		});

		placeholder.bind('contextmenu.placeholder', function(ev) {
			// don't show CK context menu
			ev.stopPropagation();
		});

		// this event is triggered by clicking [edit] in preview pop-up
		placeholder.bind('edit.placeholder', function(ev) {
			var data = $(this).getData();

			// call appriopriate editor for this type of placeholder
			switch (data.type) {
				case 'double-brackets':
					RTE.templateEditor.showTemplateEditor($(this));
					break;

				case 'comment':
					RTE.commentEditor.showCommentEditor($(this));
					break;
			}
		});

		// tracking code when hovered
		placeholder.bind('hover.placeholder', function(ev) {
			RTE.track('visualMode', self.getTrackingType($(this)), 'hover', 'init');
		});

		// setup events once more on each drag&drop
		RTE.getEditor().unbind('dropped.placeholder').bind('dropped.placeholder', function(ev) {
			var target = $(ev.target);

			// filter out non placeholders
			target = target.filter('img.placeholder');

			self.setupPlaceholder(target);
		});

		// RT #69635
		if (RTE.config.disableDragDrop) {
			RTE.tools.disableDragDrop(placeholder);
		}
	},

	// get type name for tracking code
	getTrackingType: function(placeholder) {
		var type;
		var data = $(placeholder).getData();

		switch(data.type) {
			case 'double-brackets':
				type = 'template';
				break;

			case 'comment':
				type = 'comment';
				break;

			default:
				type = 'advancedCode';
		}

		return type;
	},

	// expand placeholder preview (RT #34048)
	// FIXME - still some odd behavior here. Shouldn't expand out of editarea.
	expandPlaceholder: function(preview, placeholder) {
		var previewArea = preview.find('.RTEPlaceholderPreviewCode');

		if (previewArea.exists()) {
			var element = previewArea.get(0),
				scrollHeight = element.scrollHeight,
				scrollWidth = element.scrollWidth,
				height = previewArea.height(),
				width = previewArea.width(),
				hasHorizontalScrollBar = scrollWidth > width,
				hasVerticalScrollBar = scrollHeight > height;

//console.log('hasScrollbars', hasHorizontalScrollBar || hasVerticalScrollBar );
//console.log('scrollHeight', scrollHeight);
//console.log('scrollWidth', scrollWidth);
//console.log('height', height);
//console.log('width', width);

			// Scrollbar exists
			if (hasHorizontalScrollBar || hasVerticalScrollBar) {
				var previewOffset = preview.offset();
//console.log(previewOffset);
				// Try to get rid of horizontal scroll
				if (hasHorizontalScrollBar) {
					width = scrollWidth;
				}

				// Try to get rid of vertical scroll
				if (hasVerticalScrollBar) {
					height = scrollHeight;
				}

				// (x,y) of bottom right corner of preview
				var x = previewOffset.left + width;
				var y = previewOffset.top + height;

				// calculate editarea size
				var editarea = $('#cke_contents_wpTextbox1');
				var editareaOffset = editarea.offset();
				var minX = editareaOffset.left;
				var minY = editareaOffset.top;
				var maxX = minX + editarea.width();
				var maxY = minY + editarea.height();
//console.log('x', x);
//console.log('y', y);
//console.log('maxX', maxX);
//console.log('maxY', maxY);
				// limit preview expansion by edges of editarea
				if (x > maxX) {
					width -= (x - maxX);
				}

				if (y > maxY) {
					height -= (y - maxY);
				}
//console.log('newHeight', height);
//console.log('newWidth', width);
				// Set minimum height and width
				width = Math.max(width, 350);
				height = Math.max(height, 60);

				preview.children('.RTEPlaceholderPreviewInner').width(width);
				previewArea.addClass('RTEPlaceholderPreviewExpanded').height(height);
			} else {
				previewArea.removeClass('RTEPlaceholderPreviewExpanded');
			}
		}
	}
});
