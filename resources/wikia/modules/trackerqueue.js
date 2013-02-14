/**
 * WikiaTrackerQueue
 *
 * Usage:
 *
 * Use WikiaTrackerQueue.track, just like you would use WikiaTracker.track.
 * The difference is the WikiaTrackerQueue's track doesn't run immediately.
 * Instead it repeats the calls to WikiaTracker.track once it's ready.
 *
 * See WikiaTracker, then :-)
 */
(function (context) {
	'use strict';
	var slice = [].slice;
	context.WikiaTrackerQueue = {
		track: function() {
			var args = slice.call(arguments);
			context.wgLoaderQueue.push({
				deps: ['wikia.tracker'],
				callback: function (tracker) {
					tracker.track.apply(tracker, args);
				}
			});
		}
	};
}(this));
