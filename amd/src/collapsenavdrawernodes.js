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
 * Local plugin "Boost navigation fumbling" - JS code for collapsing nav drawer nodes
 *
 * @package    local_boostnavigation
 * @copyright  2017 Kathrin Osswald, Ulm University <kathrin.osswald@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    "use strict";

    function toggleClickHandler(node, prefname) {
        node.click(function() {
            // If the parent node is currently expanded.
            if (node.attr('data-collapse') == 0) {
                // Set the hidden attibute to true for all elements with the node as the parent attribute.
                $('.list-group-item[data-parent-key=' + prefname + ']').attr("data-hidden", "1");
                // Change the attribute collapsed of the node itself to collapsed.
                node.attr("data-collapse", "1");
                // Save this state to the equivalent user preference varibale.
                M.util.set_user_preference('local_boostnavigation-collapse_' + prefname + 'node', 1);
            } else if (node.attr('data-collapse') == 1) { // If the parent node is currently collapsed.
                // Set the hidden attibute to false for all elements with the node as the parent attribute.
                $('.list-group-item[data-parent-key=' + prefname + ']').attr("data-hidden", "0");
                // Change the attribute collapsed of the node itself to expanded.
                node.attr("data-collapse", "0");
                // Save this state to the equivalent user preference varibale.
                M.util.set_user_preference('local_boostnavigation-collapse_' + prefname + 'node', 0);
            }
        });
    }

    function initToggleNodes(prefname) {
        // Collapsing the node mycourses is enabled.
        if (prefname == 'mycourses') {
            var mycoursesnode = $('.list-group-item[data-key="mycourses"]');
            toggleClickHandler(mycoursesnode, prefname);
        }
    }

    return {
        init: function(params) {
            for (var i = 0, len = params.length; i < len; i++) {
                initToggleNodes(params[i]);
            }
        }
    };
});
