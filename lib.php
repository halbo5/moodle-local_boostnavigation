<?php
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
 * Local plugin "Boost navigation fumbling" - Library
 *
 * @package    local_boostnavigation
 * @copyright  2017 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Fumble with Moodle's global navigation by leveraging Moodle's *_extend_navigation() hook.
 *
 * @param global_navigation $navigation
 */
function local_boostnavigation_extend_navigation(global_navigation $navigation) {
    global $CFG, $PAGE;

    // Fetch config.
    $config = get_config('local_boostnavigation');

    // Include local library.
    require_once(dirname(__FILE__) . '/locallib.php');

    // Check if admin wanted us to remove the myhome node from Boost's nav drawer.
    // We have to check explicitely if the configurations are set because this function will already be
    // called at installation time and would then throw PHP notices otherwise.
    if (isset($config->removemyhomenode) && $config->removemyhomenode == true) {
        // If yes, do it.
        // Hide myhome node (which is basically the $navigation global_navigation node).
        $navigation->showinflatnavigation = false;
    }

    // Check if admin wanted us to remove the home node from Boost's nav drawer.
    if (isset($config->removehomenode) && $config->removehomenode == true) {
        // If yes, do it.
        if ($homenode = $navigation->find('home', global_navigation::TYPE_ROOTNODE)) {
            // Hide home node.
            $homenode->showinflatnavigation = false;
        }
    }

    // Check if admin wanted us to remove the calendar node from Boost's nav drawer.
    if (isset($config->removecalendarnode) && $config->removecalendarnode == true) {
        // If yes, do it.
        if ($calendarnode = $navigation->find('calendar', global_navigation::TYPE_CUSTOM)) {
            // Hide calendar node.
            $calendarnode->showinflatnavigation = false;
        }
    }

    // Check if admin wanted us to remove the privatefiles node from Boost's nav drawer.
    if (isset($config->removeprivatefilesnode) && $config->removeprivatefilesnode == true) {
        // If yes, do it.
        if ($privatefilesnode = local_boostnavigation_find_privatefiles_node($navigation)) {
            // Hide privatefiles node.
            $privatefilesnode->showinflatnavigation = false;
        }
    }

    // Check if admin wanted us to remove the mycourses node from Boost's nav drawer.
    // Or if admin wanted us to collapse the node "My courses".
    if (isset($config->removemycoursesnode) && $config->removemycoursesnode == true ||
        isset($config->collapsenodemycourses) && $config->collapsenodemycourses == true) {
        // Fetch the my courses node.
        $mycoursesnode = $navigation->find('mycourses', global_navigation::TYPE_ROOTNODE);
        // Get its children.
        $mycourseschildrennodeskeys = $mycoursesnode->get_children_key_list();
    }

    // Check if admin wanted us to remove the mycourses node from Boost's nav drawer.
    if (isset($config->removemycoursesnode) && $config->removemycoursesnode == true) {
        // If yes, do it.
        if ($mycoursesnode) {
            // Hide mycourses node.
            $mycoursesnode->showinflatnavigation = false;

            // Hide all courses below the mycourses node.
            foreach ($mycourseschildrennodeskeys as $k) {
                // If the admin decided to display categories, things get slightly complicated.
                if ($CFG->navshowmycoursecategories) {
                    // We need to find all children nodes first.
                    $allchildrennodes = local_boostnavigation_get_all_childrenkeys($mycoursesnode->get($k));
                    // Then we can hide each children node.
                    // Unfortunately, the children nodes have navigation_node type TYPE_MY_CATEGORY or navigation_node type
                    // TYPE_COURSE, thus we need to search without a specific navigation_node type.
                    foreach ($allchildrennodes as $cn) {
                        $mycoursesnode->find($cn, null)->showinflatnavigation = false;
                    }
                } else { // Otherwise we have a flat navigation tree and hiding the courses is easy.
                    $mycoursesnode->get($k)->showinflatnavigation = false;
                }
            }
        }
    }

    // Check if admin wanted us to collapse the node "My courses".
    // We won't support the setting navshowmycoursecategories here as this will set different attribute values than the default.
    if (isset($config->collapsenodemycourses) && $config->collapsenodemycourses == true
            && $CFG->navshowmycoursecategories == false) {
        if ($mycoursesnode) {
            // Remember the collapsible node for JavaScript.
            $collapsenodesforjs[] = 'mycourses';
            // Change the is-expandable attribute for the mycourses node to true.
            $mycoursesnode->isexpandable = true;
            // Get the user preference for the collapse state of the mycourses node and set equivalent attribute.
            $userprefmycourses = get_user_preferences('local_boostnavigation-collapse_mycoursesnode', 0);
            if ($userprefmycourses == 1) {
                $mycoursesnode->collapse = true;
            } else {
                $mycoursesnode->collapse = false;
            }
            // All children need their "hidden" attributes to be changed for displaying or hiding them.
            foreach ($mycourseschildrennodeskeys as $k) {
                if ($userprefmycourses == 1) {
                    $mycoursesnode->get($k)->hidden = true;
                } else {
                    $mycoursesnode->get($k)->hidden = false;
                }
            }
        }
    } else {
        // Change the is-expandable attribute for the mycourses node to false.
        $navigation->find('mycourses', global_navigation::TYPE_ROOTNODE)->isexpandable = false;
    }

    // If at least one setting to collapse a node is set.
    if (!empty($collapsenodesforjs)) {
        // Add JavaScript for collapsing nodes to the page.
        $PAGE->requires->js_call_amd('local_boostnavigation/collapsenavdrawernodes', 'init', [$collapsenodesforjs]);
        // Allow updating the necessary user preferences via Ajax.
        foreach ($collapsenodesforjs as $node) {
            user_preference_allow_ajax_update('local_boostnavigation-collapse_'. $node .'node', PARAM_BOOL);
        }
    }
}
