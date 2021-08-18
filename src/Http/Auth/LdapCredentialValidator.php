<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Http\Auth;

use LC\Portal\Exception\LdapClientException;
use LC\Portal\Http\UserInfo;
use LC\Portal\LdapAuthConfig;
use LC\Portal\LdapClient;
use LC\Portal\LoggerInterface;

class LdapCredentialValidator implements CredentialValidatorInterface
{
    private LdapAuthConfig $ldapAuthConfig;

    private LoggerInterface $logger;

    private LdapClient $ldapClient;

    public function __construct(LdapAuthConfig $ldapAuthConfig, LoggerInterface $logger, LdapClient $ldapClient)
    {
        $this->ldapAuthConfig = $ldapAuthConfig;
        $this->logger = $logger;
        $this->ldapClient = $ldapClient;
    }

    /**
     * @return false|\LC\Portal\Http\UserInfo
     */
    public function isValid(string $authUser, string $authPass)
    {
        // add "realm" after user name if none is specified
        if (null !== $addRealm = $this->ldapAuthConfig->addRealm()) {
            if (false === strpos($authUser, '@')) {
                $authUser .= '@'.$addRealm;
            }
        }

        // get bind DN either from template, or from anonymous bind + search
        if (false === $bindDn = $this->getBindDn($authUser)) {
            // unable to find a DN to bind with...
            return false;
        }

        try {
            $this->ldapClient->bind($bindDn, $authPass);

            $baseDn = $this->ldapAuthConfig->baseDn() ?? $bindDn;
            $userFilter = '(objectClass=*)';
            if (null !== $userFilterTemplate = $this->ldapAuthConfig->userFilterTemplate()) {
                $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $userFilterTemplate);
            }

            $userId = $authUser;
            $permissionList = [];

            if (null !== $userIdAttribute = $this->ldapAuthConfig->userIdAttribute()) {
                // normalize the userId by querying it from the LDAP, benefits:
                // (1) we get the exact same capitalization as in the LDAP
                // (2) we can take a completely different attribute as the user
                //     id, e.g. mail, ipaUniqueID, ...
                if (null !== $directoryUserId = $this->getUserId($baseDn, $userFilter, $userIdAttribute)) {
                    $userId = $directoryUserId;
                }
            }

            return new UserInfo(
                $userId,
                $this->getPermissionList($baseDn, $userFilter, $this->ldapAuthConfig->permissionAttributeList())
            );
        } catch (LdapClientException $e) {
            $this->logger->warning(
                sprintf('unable to bind with DN "%s" (%s)', $bindDn, $e->getMessage())
            );

            return false;
        }
    }

    private function getUserId(string $baseDn, string $userFilter, string $userIdAttribute): ?string
    {
        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            [$userIdAttribute]
        );

        // it turns out that PHP's LDAP client converts the attribute name to
        // lowercase before populating the array...
        if (isset($ldapEntries[0][strtolower($userIdAttribute)][0])) {
            return $ldapEntries[0][strtolower($userIdAttribute)][0];
        }

        return null;
    }

    /**
     * @return false|string
     */
    private function getBindDn(string $authUser)
    {
        if (null !== $bindDnTemplate = $this->ldapAuthConfig->bindDnTemplate()) {
            // we have a bind DN template to bind to the LDAP with the user's
            // provided "Username", so use that
            return str_replace('{{UID}}', LdapClient::escapeDn($authUser), $bindDnTemplate);
        }

        // we do not have a bind DN, so do an (anonymous) LDAP bind + search to
        // find a DN we can bind with based on userFilterTemplate
        $this->ldapClient->bind($this->ldapAuthConfig->searchBindDn(), $this->ldapAuthConfig->searchBindPass());
        if (null === $userFilterTemplate = $this->ldapAuthConfig->userFilterTemplate()) {
            $this->logger->error('"userFilterTemplate" not set, unable to search for DN');

            return false;
        }
        $userFilter = str_replace('{{UID}}', LdapClient::escapeDn($authUser), $userFilterTemplate);
        if (null === $baseDn = $this->ldapAuthConfig->baseDn()) {
            $this->logger->error('"baseDn" not set, unable to search for DN');

            return false;
        }
        $ldapEntries = $this->ldapClient->search($baseDn, $userFilter);
        if (!isset($ldapEntries[0]['dn'])) {
            // unable to find an entry in this baseDn with this filter
            return false;
        }

        return $ldapEntries[0]['dn'];
    }

    /**
     * @param array<string> $permissionAttributeList
     *
     * @return array<string>
     */
    private function getPermissionList(string $baseDn, string $userFilter, array $permissionAttributeList): array
    {
        if (0 === \count($permissionAttributeList)) {
            return [];
        }

        $ldapEntries = $this->ldapClient->search(
            $baseDn,
            $userFilter,
            $permissionAttributeList
        );

        $permissionList = [];
        foreach ($permissionAttributeList as $permissionAttribute) {
            // it turns out that PHP's LDAP client converts the attribute name to
            // lowercase before populating the array...
            if (isset($ldapEntries[0][strtolower($permissionAttribute)][0])) {
                $permissionList = array_merge($permissionList, \array_slice($ldapEntries[0][strtolower($permissionAttribute)], 1));
            }
        }

        return $permissionList;
    }
}
