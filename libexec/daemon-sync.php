<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\Config;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\VpnDaemon;

/*
 * This script is responsible for three things:
 * 1. (Re)add WireGuard peers when they are missing, e.g. after a node reboot
 * 2. Delete WireGuard peers with expired configurations
 * 3. Disconnect OpenVPN clients with expired certificates
 *
 * This script interfaces with `vpn-daemon` running on the node(s). It will
 * first figure out which peers/clients should be there and remove/disconnect
 * the ones that should NOT be there (anymore).
 *
 * Due to the architecture, e.g. multiple profiles can use the same vpn-daemon
 * and the vpn-daemon has no concept of "profiles" the administration is a bit
 * complicated. A list of peers/clients is created linked to a "nodeUrl",
 * i.e. the URL for connecting to vpn-daemon that belongs to a profile and
 * afterwards perform add/remove/disconnect what is necessary.
 */

$logger = new SysLogger('vpn-user-portal');

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage(
        new PDO(
            $config->dbConfig($baseDir)->dbDsn(),
            $config->dbConfig($baseDir)->dbUser(),
            $config->dbConfig($baseDir)->dbPass()
        ),
        $baseDir.'/schema'
    );
    $vpnDaemon = new VpnDaemon(new CurlHttpClient(), $logger);

    $peerListByNode = [];
    $certListByNode = [];

    // XXX :we should delete from the database the WireGuard peer
    // configurations with IP addresses that are out of the range/range6 of the
    // particular profile. I guess
    // technically they won't work anymore in most cases, but if you do some
    // IP reassignment it might be possible to have a client connect to another
    // profile...
    foreach ($config->profileConfigList() as $profileConfig) {
        // XXX consider multiple nodes per profile!
        $nodeUrl = $profileConfig->nodeUrl(0);
        // XXX this is not bound to the nodeUrl in the DB, so we have to be a bit more clever here... the range(6) indicates which nodeUrl it belong to...
        if ('wireguard' === $profileConfig->vpnProto()) {
            if (!array_key_exists($nodeUrl, $peerListByNode)) {
                $peerListByNode[$nodeUrl] = [];
            }
            $wPeerListByProfileId = $storage->wPeerListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED);
            $peerListByNode[$nodeUrl] = array_merge($peerListByNode[$nodeUrl], $wPeerListByProfileId);
        }
        if ('openvpn' === $profileConfig->vpnProto()) {
            if (!array_key_exists($nodeUrl, $certListByNode)) {
                $certListByNode[$nodeUrl] = [];
            }
            $oCertListByProfileId = $storage->oCertListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED);
            $certListByNode[$nodeUrl] = array_merge($certListByNode[$nodeUrl], $oCertListByProfileId);
        }
    }

    foreach ($peerListByNode as $nodeUrl => $peerList) {
        $wPeerList = $vpnDaemon->wPeerList($nodeUrl, true);
        $publicKeyListToAdd = array_diff(array_keys($peerList), array_keys($wPeerList));
        foreach ($publicKeyListToAdd as $publicKey) {
            //echo sprintf('**ADD** [%s]: %s (%s,%s)', $nodeUrl, $publicKey, $peerList[$publicKey]['ip_four'], $peerList[$publicKey]['ip_six']).PHP_EOL;
            $vpnDaemon->wPeerAdd(
                $nodeUrl,
                $publicKey,
                $peerList[$publicKey]['ip_four'],
                $peerList[$publicKey]['ip_six']
            );
        }

        $publicKeyListToRemove = array_diff(array_keys($wPeerList), array_keys($peerList));
        foreach ($publicKeyListToRemove as $publicKey) {
            //echo sprintf('**REMOVE** [%s]: %s', $nodeUrl, $publicKey).PHP_EOL;
            $vpnDaemon->wPeerRemove(
                $nodeUrl,
                $publicKey
            );
        }
    }

    foreach ($certListByNode as $nodeUrl => $certList) {
        $oConnectionList = $vpnDaemon->oConnectionList($nodeUrl);
        $commonNameListToDisconnect = array_diff(array_keys($oConnectionList), array_keys($certList));
        foreach ($commonNameListToDisconnect as $commonName) {
            //echo sprintf('**DISCONNECT** [%s]: %s', $nodeUrl, $commonName).PHP_EOL;
            $vpnDaemon->oDisconnectClient(
                $nodeUrl,
                $commonName
            );
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
