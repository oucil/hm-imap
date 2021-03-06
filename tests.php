<?php

/**
 * tests.php
 *
 * Simple unit test suite for the Hm_IMAP class. requires an IMAP account with
 * known content for all the tests to run properly (if you setup the config to
 * a valid local account it's easy to adjust the expected results to make all
 * the tests pass). Every public method has at least one test in this file.
 */

/* check for username and password */
if ( isset( $argv[2] ) ) {
    $username = $argv[1];
    $password = $argv[2];
}
else {
    die( "\nyou need to pass your IMAP username and password to this script:\n".
         "\nphp ./tests.php jason 123456\n\n" );
}

/* include the lib */
require('hm-imap.php');

/* default server properties */
$server = '127.0.0.1';
$port = 143;
$passed = 0;

/* show all errors and set a default tz */
error_reporting ( E_ALL | E_STRICT );
date_default_timezone_set( 'UTC' );

/* RUN TESTS */
$imap = new Hm_IMAP();
assert_equal( true, is_object( $imap ) );

$connect = $imap->connect([
    'username' => $username,
    'password' => $password,
    'server' => $server,
    'port' => $port
]);
assert_equal( true, $connect );
assert_equal( 'authenticated', $imap->get_state() );

$caps = $imap->get_capability();
assert_equal( true, strstr( $caps, 'CAPABILITY' ) );

$mailbox_list = $imap->get_mailbox_list();
assert_equal( true, isset($mailbox_list['INBOX']));

$status = $imap->get_mailbox_status( 'INBOX' );
assert_equal( true, isset( $status['messages'] ) );
assert_equal( true, ctype_digit( $status['messages'] ) );

$folder_detail = $imap->select_mailbox( 'INBOX' );
assert_equal( 1, $folder_detail['selected'] );
assert_equal( 'selected', $imap->get_state() );

$poll_results = $imap->poll();
assert_equal( true, empty( $poll_results ) );

$unseen_uids = $imap->search('UNSEEN');
assert_equal( true, is_array( $unseen_uids) );
assert_equal( true, !empty( $unseen_uids ) );
assert_equal( true, ctype_digit( $unseen_uids[0] ) );

$search_res = $imap->search( 'ALL', '1:100', 'To', 'jason' );
assert_equal( true, is_array( $search_res ) );
assert_equal( true, !empty( $search_res ) );
assert_equal( true, ctype_digit( $search_res[0] ) );

$msg_list = $imap->get_message_list( array( 3 ) );
assert_equal( true, isset( $msg_list[3] ) );
assert_equal( 1, count( $msg_list ) );

$struct = $imap->get_message_structure( 3 );
assert_equal( true, is_array( $struct ) );
assert_equal( true, !empty( $struct ) );
assert_equal( 'text', $struct[1]['type'] );

$flat = $imap->flatten_bodystructure( $struct );
assert_equal( array( 1 => 'text/plain'), $flat );

$struct_part = $imap->search_bodystructure( $struct, ['type' => 'text', 'subtype' => 'plain']);
assert_equal( $struct, $struct_part );

$headers = $imap->get_message_headers( 3, 1 );
assert_equal( true, is_array( $headers ) );
assert_equal( true, !empty( $headers ) );
assert_equal( 'jason@shop.localdomain', $headers['To'] ); 

$size = $imap->start_message_stream( 3, 1 );
assert_equal( 10, $size ); 

while ( $text = $imap->read_stream_line() ) {
    assert_equal( true, strlen( $text ) > 0 );
}
assert_equal( false, $text );

$page = $imap->get_mailbox_page('INBOX', 'ARRIVAL', true, 'ALL', 0, 5);
assert_equal( true, is_array( $page ) );
assert_equal( 5, count( $page ) );

$msg = $imap->get_message_content( 3, 1 );
assert_equal( true, strlen( $msg ) > 0 );

$txt_msg = $imap->get_first_message_part( 3, 'text', 'plain' );
assert_equal( true, strlen( $txt_msg ) );

$fld = $imap->decode_fld( 'test' );
assert_equal( 'test', $fld );

$fld = $imap->decode_fld( '=?UTF-8?B?amFzb24=?=' );
assert_equal( 'jason', $fld );

$seq = $imap->convert_array_to_sequence( array( 1, 2, 3, 4, 5, 10, 11, 15, 20 ) );
assert_equal( $seq, '1:5,10:11,15,20' );

$list = $imap->convert_sequence_to_array( '1:10,70' );
assert_equal( $list, array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 70 ) );

$sorted_uids = $imap->sort_by_fetch( 'ARRIVAL', true, 'UNSEEN' );
assert_equal( true, is_array( $sorted_uids ) );
assert_equal( true, !empty( $sorted_uids ) );
assert_equal( 18, $sorted_uids[0] );

if ( $imap->is_supported( 'ENABLE' ) ) {
    $enable_results = $imap->enable();
    assert_equal( true, in_array( 'QRESYNC', $enable_results ) );
}

if ( $imap->is_supported( 'ESEARCH' ) ) {
    $esearch_res = $imap->search( 'ALL', false, false, false, array( 'MIN', 'MAX', 'COUNT', 'ALL' ) );
    assert_equal( true, isset( $esearch_res['min'] ) );
    assert_equal( 1, $esearch_res['min'] );
    assert_equal( 25, $esearch_res['max'] );
    assert_equal( 25, $esearch_res['count'] );
    assert_equal( '1:25', $esearch_res['all'] );
}

if ( $imap->is_supported( 'ID' ) ) {
    $id = $imap->id();
    assert_equal( true, is_array( $id ) );
}

if ( $imap->is_supported( 'SORT' ) ) {
    $sorted_uids = $imap->get_message_sort_order( 'ARRIVAL' );
    assert_equal( true, is_array( $sorted_uids ) );
    assert_equal( true, !empty( $sorted_uids ) );
    assert_equal( true, ctype_digit( $sorted_uids[0] ) );
}
if ( $imap->is_supported( 'ESORT' ) ) {
    $esort_res = $imap->get_message_sort_order( 'ARRIVAL', true, 'ALL', array( 'MIN', 'MAX', 'COUNT', 'ALL' ) );
    assert_equal( true, isset( $esort_res['min'] ) );
    assert_equal( 1, $esort_res['min'] );
    assert_equal( 25, $esort_res['max'] );
    assert_equal( 25, $esort_res['count'] );
    assert_equal( '1:25', $esort_res['all'] );
}

if ( $imap->is_supported( 'NAMESPACE' ) ) {
    $nspaces = $imap->get_namespaces();
    assert_equal( true, is_array( $nspaces ) );
    assert_equal( true, !empty( $nspaces ) );
    assert_equal( '/', $nspaces[0]['delim'] );
}

if ( $imap->is_supported( 'X-GM-EXT-1' ) ) {
    $unread = $imap->google_search( 'in:unread' );
    assert_equal( true, !empty( $unread ) );
}

if ( $imap->is_supported( 'QUOTA' ) ) {
    $quotas = $imap->get_quota();
    assert_equal( true, !empty( $quotas ) );
    $quotas = $imap->get_quota_root( 'INBOX' );
    assert_equal( true, !empty( $quotas ) );
}

$created = $imap->create_mailbox( 'test123456789/' );
assert_equal( true, $created );

$created = $imap->create_mailbox( 'test123456789/subfolder/' );
assert_equal( true, $created );

$created = $imap->create_mailbox( 'test123456789/subfolder/another_folder' );
assert_equal( true, $created );

$level_list = $imap->get_folder_list_by_level( 'test123456789/' );
assert_equal( true, !empty( $level_list ) );

$deleted = $imap->delete_mailbox( 'test123456789/subfolder/another_folder' );
assert_equal( true, $deleted );

$deleted = $imap->delete_mailbox( 'test123456789/subfolder' );
assert_equal( true, $deleted );

$renamed = $imap->rename_mailbox( 'test123456789', 'test987654321' );
assert_equal( true, $renamed );

$deleted = $imap->delete_mailbox( 'test987654321' );
assert_equal( true, $deleted );

$flagged = $imap->message_action('FLAG', array( 3 ) );
assert_equal( true, $flagged );

$headers = $imap->get_message_headers( 3, 1 );
assert_equal( true, stristr($headers['Flags'], 'flagged' ) );

$unflagged = $imap->message_action('UNFLAG', array( 3 ) );
assert_equal( true, $unflagged );

$unselect = $imap->unselect_mailbox();
assert_equal( true, $unselect );

$imap->disconnect();
assert_equal( 'disconnected', $imap->get_state() );

$cache = $imap->dump_cache();
$imap->bust_cache( 'ALL' );
$imap->load_cache( $cache );
assert_equal( true, strlen($cache) > 0 );

$debug = $imap->show_debug( false, true );
assert_equal( false, stristr( $debug, 'FAILED' ) );

printf( "\nTests passed: %d\n\n", $passed );
$imap->show_debug();

/* helper function for test result checking */
function assert_equal( $expected, $actual ) {
    global $passed, $imap;
    if ( $actual != $expected ) {
        $imap->show_debug( true );
        debug_print_backtrace();
        die(sprintf("assert_equal failed\nexpected: %s\nactual: %s\n",
            $expected, $actual));
    }
    else {
        $passed++;
    }
}
?>
