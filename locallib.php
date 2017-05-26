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
 * Local plugin "Boost navigation fumbling" - Local Library
 *
 * @package    local_boostnavigation
 * @copyright  2017 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle core does not add a key to the privatefiles node when adding it to the navigation,
 * so we need to find it with some overhead.
 *
 * @param global_navigation $navigation
 * @return navigation_node
 */
function local_boostnavigation_find_privatefiles_node(global_navigation $navigation) {
    // Get front page course node.
    if ($coursenode = $navigation->find('1', null)) {
        // Get children of the front page course node.
        $coursechildrennodeskeys = $coursenode->get_children_key_list();

        // Get text string to look for.
        $needle = get_string('privatefiles');

        // Check all children to find the privatefiles node.
        foreach ($coursechildrennodeskeys as $k) {
            // Get child node.
            $childnode = $coursenode->get($k);
            // Check if we have found the privatefiles node.
            if ($childnode->text == $needle) {
                // If yes, return the node.
                return $childnode;
            }
        }
    }

    // This should not happen.
    return false;
}


/**
 * Moodle core does not have a built-in functionality to get all keys of all children of a navigation node,
 * so we need to get these ourselves.
 *
 * @param navigation_node $navigationnode
 * @return array
 */
function local_boostnavigation_get_all_childrenkeys(navigation_node $navigationnode) {
    // Empty array to hold all children.
    $allchildren = array();

    // No, this node does not have children anymore.
    if (count($navigationnode->children) == 0) {
        return array();
    } else { // Yes, this node has children.
        // Get own own children keys.
        $childrennodeskeys = $navigationnode->get_children_key_list();
        // Get all children keys of our children recursively.
        foreach ($childrennodeskeys as $ck) {
            $allchildren = array_merge($allchildren, local_boostnavigation_get_all_childrenkeys($navigationnode->get($ck)));
        }
        // And add our own children keys to the result.
        $allchildren = array_merge($allchildren, $childrennodeskeys);

        // Return everything.
        return $allchildren;
    }
}

/**
 * Function to fill the section cache for local_boostnavigation.
 *
 * @param navigation_node $navigationnode
 * @param array $nodechildrenkeys
 * @param int $courseid
 */
function local_boostnavigation_fill_section_cache(navigation_node $parentnode, array $nodechildrenkeys, $courseid) {
    // Use the cache "local_boostnavigation_section_cache".
    $localboostnavigationsectioncache = cache::make('local_boostnavigation', 'local_boostnavigation_section_cache');
    // Traverse the navigationnode children.
    foreach ($nodechildrenkeys as $k) {
        // Get the href URLs.
        $url = $parentnode->get($k)->action->out_as_local_url();
        // Make array on delimiter "#, because after this the section ids are defined.
        $urlparams = explode('#', $url);
        // If there is an entry behind the delimiter #, we have menu items with a section link.
        if (!empty($urlparams[1])) {
            // If there's a sction-0 save the section id in the cache and don't go futrher.
            if ($urlparams[1] == "section-0") {
                $cachevalue[$courseid] = $k;
                $localboostnavigationsectioncache->set('local_boostnavigation_section_cache', $cachevalue);
                break;
                // Otherwise we have no section-0 but a section-1 as first section.
            } else if (($urlparams[1] != "section-0") && ($urlparams[1] == "section-1")) {
                $cachevalue[$courseid] = $k;
                $localboostnavigationsectioncache->set('local_boostnavigation_section_cache', $cachevalue);
            }
        }
    }
}
