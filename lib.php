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
    global $PAGE, $CFG, $COURSE;

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

    // Get the my courses node.
    $mycoursesnode = $navigation->find('mycourses', global_navigation::TYPE_ROOTNODE);
    // Get it's children.
    $mycourseschildrennodeskeys = $mycoursesnode->get_children_key_list();

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

    // Check if admin wanted us to make it possible to toggle specific nodes in Boost's nav drawer.
    if (isset($config->togglenodemycourses) || isset($config->togglecoursehome)) {
         // Check if admin wanted us to be able to toggle the node "My Courses".
        if (isset($config->togglenodemycourses) && $config->togglenodemycourses == true) {
            // If yes, do it.
            $togglenodesforjs[] = 'mycourses';
            // Get the user preference for the toggle state of the mycourses node and set equivalent classes.
            if (get_user_preferences('local_boostnavigation-collapse_mycoursesnode', 0) == 0) {
                    $mycoursesnode->add_class('triangle-down node-expanded');
            } else {
                    $mycoursesnode->add_class('triangle-up node-collapsed');
            }

            // All children need classes for displaying or hiding those.
            foreach ($mycourseschildrennodeskeys as $k) {
                // If the admin decided to display categories, things get slightly complicated.
                if ($CFG->navshowmycoursecategories) {
                    // We need to find all children nodes first.
                    $allchildrennodes = local_boostnavigation_get_all_childrenkeys($mycoursesnode->get($k));
                    // Then we can add the equivalent classes.
                    // Unfortunately, the children nodes have navigation_node type TYPE_MY_CATEGORY or navigation_node type
                    // TYPE_COURSE, thus we need to search without a specific navigation_node type.
                    foreach ($allchildrennodes as $cn) {
                        if (get_user_preferences('local_boostnavigation-collapse_mycoursesnode') == 0) {
                            $mycoursesnode->find($cn, null)->add_class("node-visible");
                        } else {
                            $mycoursesnode->find($cn, null)->add_class("node-hidden");
                        }
                    }
                } else { // Otherwise we have a flat navigation tree and hiding the courses is easy.
                    if (get_user_preferences('local_boostnavigation-collapse_mycoursesnode') == 0) {
                            $mycoursesnode->get($k)->add_class("node-visible");
                    } else {
                            $mycoursesnode->get($k)->add_class("node-hidden");
                    }
                }
            }
        }

        // Check if admin wanted us to be able to toggle the node "Sections".
        if (isset($config->togglenodecoursesections) && $config->togglenodecoursesections == true && $COURSE->id > 1) {
            // If yes, do it.
            $togglenodesforjs[] = 'sections';
            $firstsection = '';
            // Fetch course home node.
            $coursehomenode = $PAGE->navigation->find($COURSE->id, navigation_node::TYPE_COURSE);

            // Create new navigation node "Sections".
            $navnode = navigation_node::create(get_string('sections', 'moodle'), '/view.php?id='.$COURSE->id,
                        global_navigation::TYPE_CUSTOM, null, 'localboostnavigationsections', null);

            // Add the node to the course's navigation tree.
            // Get the children nodes for the coursehome node.
            $coursehomenodechildrenkeys = $coursehomenode->get_children_key_list();
            // Use caching.
            $localboostnavigationsectioncache = cache::make('local_boostnavigation', 'local_boostnavigation_section_cache');
            // Get eventually already cached entries.
            $cache = $localboostnavigationsectioncache->get('local_boostnavigation_section_cache');

            // If the cache is already filled for the current course id, then use this value.
            if (!empty($cache[$COURSE->id]) && array_search($cache[$COURSE->id], $coursehomenodechildrenkeys) != false) {
                $firstsection = $cache[$COURSE->id];
            } else {
                // Fill cache.
                local_boostnavigation_fill_section_cache($coursehomenode, $coursehomenodechildrenkeys, $COURSE->id);
                $cache = $localboostnavigationsectioncache->get('local_boostnavigation_section_cache');
                $firstsection = $cache[$COURSE->id];
            }
            // Only add the node if there is at least one section and add the node before it.
            if (!empty($firstsection)) {
                 $coursehomenode->add_node($navnode, $firstsection);
            }
            // Get the user preference for the toggle state of the sections node and set equivalent classes.
            if (get_user_preferences('local_boostnavigation-collapse_sectionsnode', 0) == 0) {
                $navnode->add_class('triangle-down node-expanded');
            } else {
                $navnode->add_class('triangle-up node-collapsed');
            }

            // Get the childkeys again because we added the node "Sections".
            $coursehomenodechildrenkeys = $coursehomenode->get_children_key_list();

            // All children need classes for displaying or hiding those.
            // Get the offset (node "Sections + 1 item").
            $offset = array_search('localboostnavigationsections', $coursehomenodechildrenkeys) + 1;
            // Slice array "coursehomenodechildrenkeys" to start after the newly integrated sectionnode.
            $sectionnodechildrenkeys = array_slice($coursehomenodechildrenkeys, $offset);

            foreach ($sectionnodechildrenkeys as $k) {
                // If the admin decided to display categories, things get slightly complicated.
                if ($CFG->navshowmycoursecategories) {
                    // We need to find all children nodes first.
                    $allchildrennodes = local_boostnavigation_get_all_childrenkeys($coursehomenode->get($k));
                    // Then we can add the equivalent classes.
                    // Unfortunately, the children nodes have navigation_node type TYPE_MY_CATEGORY or navigation_node type
                    // TYPE_COURSE, thus we need to search without a specific navigation_node type.
                    foreach ($allchildrennodes as $cn) {
                        if (get_user_preferences('local_boostnavigation-collapse_sectionsnode') == 0) {
                            $coursehomenode->find($cn, null)->add_class("node-visible");
                        } else {
                            $coursehomenode->find($cn, null)->add_class("node-hidden");
                        }
                    }
                } else { // Otherwise we have a flat navigation tree and hiding the courses is easy.
                    if (get_user_preferences('local_boostnavigation-collapse_sectionsnode') == 0) {
                            $coursehomenode->get($k)->add_class("node-visible");
                    } else {
                            $coursehomenode->get($k)->add_class("node-hidden");
                    }
                }
            }
        }

        $PAGE->requires->js_call_amd('local_boostnavigation/togglenavdrawernodes', 'init', [$togglenodesforjs]);
        user_preference_allow_ajax_update('local_boostnavigation-collapse_mycoursesnode', PARAM_BOOL);
        user_preference_allow_ajax_update('local_boostnavigation-collapse_sectionsnode', PARAM_BOOL);
    }
}
