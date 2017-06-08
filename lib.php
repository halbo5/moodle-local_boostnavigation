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

    // Catch the my courses node and its children here, because we need the result in the following functions.
    if (isset($config->removemycoursesnode) && $config->removemycoursesnode == true ||
        isset($config->togglenodemycourses) && $config->togglenodemycourses == true) {
        // Get the my courses node.
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
                }
                // Otherwise we have a flat navigation tree and hiding the courses is easy.
                else {
                    $mycoursesnode->get($k)->showinflatnavigation = false;
                }
            }
        }
    }

    // Check if admin wants to insert the node "Sections" in the Boost nav drawer.
    if (isset($config->addnodecoursesections) && $config->addnodecoursesections == true) {
        // Only proceed if we are inseide a course.
        if ($config->addnodecoursesections == true && $COURSE->id > 1) {
            $firstsection = '';
            // Fetch course home node.
            $coursehomenode = $PAGE->navigation->find($COURSE->id, navigation_node::TYPE_COURSE);
            // Get the children nodes for the coursehome node.
            $coursehomenodechildrenkeys = $coursehomenode->get_children_key_list();
            // Use caching.
            $localboostnavigationsectioncache = cache::make('local_boostnavigation', 'section_cache');
            // Get eventually already cached entries.
            $cache = $localboostnavigationsectioncache->get('section_cache');

            // If the cache is already filled for the current course id, then use this value.
            if (!empty($cache[$COURSE->id]) && array_search($cache[$COURSE->id], $coursehomenodechildrenkeys) != false) {
                $firstsection = $cache[$COURSE->id];
            } else {
                // Fill cache.
                local_boostnavigation_fill_section_cache($coursehomenode, $coursehomenodechildrenkeys, $COURSE->id);
                $cache = $localboostnavigationsectioncache->get('section_cache');
                $firstsection = $cache[$COURSE->id];
            }
            // Only proceed if the course has sections.
            if (!empty($firstsection)) {
                // Create new navigation node "Sections".
                $sectionnode = navigation_node::create(get_string('sections', 'moodle'), 'view.php?id='.$COURSE->id,
                    global_navigation::TYPE_CUSTOM, null, 'localboostnavigationsections', null);
                // Add the node before the first section.
                $coursehomenode->add_node($sectionnode, $firstsection);
            }
        }
    }

    // Check if admin wanted us to make it possible to toggle specific nodes in Boost's nav drawer.
    if (isset($config->togglenodemycourses) || isset($config->togglecoursehome) &&
        ($config->togglenodecoursesections == true || $config->togglenodemycourses == true)) {
        // Check if admin wanted us to be able to toggle the node "My Courses".
        if ($config->togglenodemycourses == true) {
            // Remember the toggled node for JavaScript.
            $togglenodesforjs[] = 'mycourses';
            // Get the user preference for the toggle state of the mycourses node and set equivalent classes.
            $userprefmycourses = get_user_preferences('local_boostnavigation-collapse_mycoursesnode', 0);
            if ($userprefmycourses == 0) {
                $mycoursesnode->add_class('node-expanded');
            } else {
                $mycoursesnode->add_class('node-collapsed');
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
                        if ($userprefmycourses == 0) {
                            $mycoursesnode->find($cn, null)->add_class("node-visible");
                        } else {
                            $mycoursesnode->find($cn, null)->add_class("node-hidden");
                        }
                    }
                } else { // Otherwise we have a flat navigation tree and hiding the courses is easy.
                    if ($userprefmycourses == 0) {
                        $mycoursesnode->get($k)->add_class("node-visible");
                    } else {
                        $mycoursesnode->get($k)->add_class("node-hidden");
                    }
                }
            }
        }

        // Check if admin enabled the inserting of the "Sections", wants us to be able to toggle this node
        // and if we are inside a course.
        if ($config->addnodecoursesections == true && $config->togglenodecoursesections == true && $COURSE->id > 1) {
            // Remember the toggled node for JavaScript.
            $togglenodesforjs[] = 'sections';

            // Only proceed if there is the node sections within the navigation object.
            // This should be the case because we check if addnodecoursesections is enabled and there the node will be added.
            if ($navigation->find('localboostnavigationsections', global_navigation::TYPE_CUSTOM)) {
                // Get the user preference for the toggle state of the sections node and set equivalent classes.
                $userprefsections = get_user_preferences('local_boostnavigation-collapse_sectionsnode', 0);
                if ($userprefsections == 0) {
                    $sectionnode->add_class('node-expanded');
                } else {
                    $sectionnode->add_class('node-collapsed');
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
                            if ($userprefsections == 0) {
                                $coursehomenode->find($cn, null)->add_class("node-visible");
                            } else {
                                $coursehomenode->find($cn, null)->add_class("node-hidden");
                            }
                        }
                    } else { // Otherwise we have a flat navigation tree and hiding the courses is easy.
                        if ($userprefsections == 0) {
                            $coursehomenode->get($k)->add_class("node-visible");
                        } else {
                            $coursehomenode->get($k)->add_class("node-hidden");
                        }
                    }
                }
            }
        }

        // If at least one of the options to toggle the mycourses or the sections node is set.
        if (!empty($togglenodesforjs)) {
            // Add JavaScript for toggeling the nodes to the page.
            $PAGE->requires->js_call_amd('local_boostnavigation/togglenavdrawernodes', 'init', [$togglenodesforjs]);
            // Allow updating the necessary user preferences via Ajax.
            foreach($togglenodesforjs as $node) {
            user_preference_allow_ajax_update('local_boostnavigation-collapse_'. $node .'node', PARAM_BOOL);
            }
        }
    }
}
