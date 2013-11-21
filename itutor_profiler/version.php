<?php

/**
 * Form for editing HTML block instances.
 *
 * @package   block_itutor_profiler
 * @copyright 2012 onwards Karsten Øster Lundqvist, University of Reading, ITUTOR
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2013090500;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2010112900;        // Requires this Moodle version
$plugin->component = 'block_itutor_profiler';      // Full name of the plugin (used for diagnostics)
$plugin->cron = 86400; // 24hr cron
