// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local plugin "Boost navigation fumbling" - JS code for toggeling nav drawer nodes
 *
 * @package    local_boostnavigation
 * @copyright  2017 Kathrin Osswald, Ulm University <kathrin.osswald@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    "use strict";

    function initToggleNodes(prefname) {
        if (prefname == 'mycourses') {
            var mycoursesnode = $('.list-group-item[data-key|=mycourses]');
            toggleClickHandler(mycoursesnode, prefname);
        }
        if (prefname == 'sections') {
            var sectionsnode = $('.list-group-item[data-key|=localboostnavigationsections]');
            sectionsnode.removeAttr('href');
            toggleClickHandler(sectionsnode, prefname);
        }
    }

    function toggleClickHandler(node, prefname) {
        node.click(function() {
            var nextAll = node.nextAll('.list-group-item');
            if (node.hasClass('node-expanded')) {
                node.removeClass('node-expanded');
                nextAll.each(function() {
                    // Only apply the changes for those items that already have the class.
                    // This prevents adding the class to items that should not be toggled.
                    if ($(this).hasClass('node-visible')) {
                        $(this).removeClass('node-visible').addClass('node-hidden');
                    }
                });
                node.addClass('node-collapsed');
                M.util.set_user_preference('local_boostnavigation-collapse_' + prefname + 'node', 1);
            } else if (node.hasClass('node-collapsed')) {
                node.removeClass('node-collapsed');
                nextAll.each(function() {
                    // Only apply the changes for those items that already have the class.
                    // This prevents adding the class to items that should not be toggled.
                    if ($(this).hasClass('node-hidden')) {
                        $(this).removeClass('node-hidden').addClass('node-visible');
                    }
                });
                node.addClass('node-expanded');
                M.util.set_user_preference('local_boostnavigation-collapse_' + prefname + 'node', 0);
            }
        });
    }

    return {
        init: function(params) {
            for (var i = 0, len = params.length; i < len; i++) {
                initToggleNodes(params[i]);
            }
        }
    };
});
