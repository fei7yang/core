@api @TestAlsoOnExternalUserBackend
Feature: auth

  Background:
    Given user "user0" has been created with default attributes
    And a new client token for "user0" has been generated

	# FILES APP
  @smokeTest
  Scenario: access files app anonymously
    When a user requests "/index.php/apps/files" with "GET" and no authentication
    Then the HTTP status code should be "401"

  @smokeTest
  Scenario: access files app with basic auth
    When user "user0" requests "/index.php/apps/files" with "GET" using basic auth
    Then the HTTP status code should be "200"

  @smokeTest
  Scenario: access files app with basic token auth
    When user "user0" requests "/index.php/apps/files" with "GET" using basic token auth
    Then the HTTP status code should be "200"

  @smokeTest
  Scenario: access files app with a client token
    When the user requests "/index.php/apps/files" with "GET" using the generated client token
    Then the HTTP status code should be "200"

  @smokeTest
  Scenario: access files app with browser session
    Given a new browser session for "user0" has been started
    When the user requests "/index.php/apps/files" with "GET" using the browser session
    Then the HTTP status code should be "200"

  @smokeTest
  Scenario: access files app with an app password
    Given a new browser session for "user0" has been started
    And the user has generated a new app password named "my-client"
    When the user requests "/index.php/apps/files" with "GET" using the generated app password
    Then the HTTP status code should be "200"

	# WebDAV

  Scenario: using WebDAV anonymously
    When a user requests "/remote.php/webdav" with "PROPFIND" and no authentication
    Then the HTTP status code should be "401"

  Scenario: using WebDAV with basic auth
    When user "user0" requests "/remote.php/webdav" with "PROPFIND" using basic auth
    Then the HTTP status code should be "207"

  Scenario: using WebDAV with token auth
    When user "user0" requests "/remote.php/webdav" with "PROPFIND" using basic token auth
    Then the HTTP status code should be "207"

	# DAV token auth is not possible yet
	#Scenario: using WebDAV with a client token
	#	When requesting "/remote.php/webdav" with "PROPFIND" using a client token
	#	Then the HTTP status code should be "207"

  Scenario: using WebDAV with browser session
    Given a new browser session for "user0" has been started
    When the user requests "/remote.php/webdav" with "PROPFIND" using the browser session
    Then the HTTP status code should be "207"


	# OCS
  @issue-32068
  Scenario Outline: using OCS anonymously
    When a user requests "<endpoint>" with "GET" and no authentication
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/apps/files_external/api/v1/mounts               | 997      | 401       |
      |/ocs/v2.php/apps/files_external/api/v1/mounts               | 997      | 401       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares         | 997      | 401       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares         | 997      | 401       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending | 997      | 401       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending | 997      | 401       |
      |/ocs/v1.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v2.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v1.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v2.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v1.php/cloud/users                                     | 997      | 401       |
      |/ocs/v2.php/cloud/users                                     | 997      | 401       |
      |/ocs/v1.php/config                                          | 100      | 200       |
      |/ocs/v2.php/config                                          | 200      | 200       |
      |/ocs/v1.php/privatedata/getattribute                        | 997      | 401       |
      |/ocs/v2.php/privatedata/getattribute                        | 997      | 401       |

  #merge into previous scenario when fixed
  @issue-34626
  Scenario: using OCS anonymously
    When a user requests "/ocs/v1.php/apps/files_sharing/api/v1/shares" with "GET" and no authentication
    Then the HTTP status code should be "200"
    #Then the HTTP status code should be "401"
    #And the OCS status code should be "997"

  Scenario Outline: using OCS with non-admin basic auth
    When user "user0" requests "<endpoint>" with "GET" using basic auth
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/apps/files_external/api/v1/mounts               | 100      | 200       |
      |/ocs/v2.php/apps/files_external/api/v1/mounts               | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares         | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares         | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/shares                | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/shares                | 200      | 200       |
      |/ocs/v1.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v2.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v1.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v2.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v1.php/cloud/users                                     | 997      | 401       |
      |/ocs/v2.php/cloud/users                                     | 997      | 401       |
      |/ocs/v1.php/config                                          | 100      | 200       |
      |/ocs/v2.php/config                                          | 200      | 200       |
      |/ocs/v1.php/privatedata/getattribute                        | 100      | 200       |
      |/ocs/v2.php/privatedata/getattribute                        | 200      | 200       |

  Scenario Outline: using OCS with admin basic auth
    When the administrator requests "<endpoint>" with "GET" using basic auth
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/cloud/apps                                      | 100      | 200       |
      |/ocs/v2.php/cloud/apps                                      | 200      | 200       |
      |/ocs/v1.php/cloud/groups                                    | 100      | 200       |
      |/ocs/v2.php/cloud/groups                                    | 200      | 200       |
      |/ocs/v1.php/cloud/users                                     | 100      | 200       |
      |/ocs/v2.php/cloud/users                                     | 200      | 200       |


  Scenario Outline: using OCS with token auth of a normal user
    When user "user0" requests "<endpoint>" with "GET" using basic token auth
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/apps/files_external/api/v1/mounts               | 100      | 200       |
      |/ocs/v2.php/apps/files_external/api/v1/mounts               | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares         | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares         | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/shares                | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/shares                | 200      | 200       |
      |/ocs/v1.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v2.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v1.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v2.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v1.php/cloud/users                                     | 997      | 401       |
      |/ocs/v2.php/cloud/users                                     | 997      | 401       |
      |/ocs/v1.php/config                                          | 100      | 200       |
      |/ocs/v2.php/config                                          | 200      | 200       |
      |/ocs/v1.php/privatedata/getattribute                        | 100      | 200       |
      |/ocs/v2.php/privatedata/getattribute                        | 200      | 200       |

  Scenario Outline: using OCS with client token of a normal user
    When the user requests "<endpoint>" with "GET" using the generated client token
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/apps/files_external/api/v1/mounts               | 100      | 200       |
      |/ocs/v2.php/apps/files_external/api/v1/mounts               | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares         | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares         | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/shares                | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/shares                | 200      | 200       |
      |/ocs/v1.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v2.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v1.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v2.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v1.php/cloud/users                                     | 997      | 401       |
      |/ocs/v2.php/cloud/users                                     | 997      | 401       |
      |/ocs/v1.php/config                                          | 100      | 200       |
      |/ocs/v2.php/config                                          | 200      | 200       |
      |/ocs/v1.php/privatedata/getattribute                        | 100      | 200       |
      |/ocs/v2.php/privatedata/getattribute                        | 200      | 200       |

  Scenario Outline: using OCS with browser session of a normal user
    Given a new browser session for "user0" has been started
    When the user requests "<endpoint>" with "GET" using the browser session
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/apps/files_external/api/v1/mounts               | 100      | 200       |
      |/ocs/v2.php/apps/files_external/api/v1/mounts               | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares         | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares         | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/shares                | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/shares                | 200      | 200       |
      |/ocs/v1.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v2.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v1.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v2.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v1.php/cloud/users                                     | 997      | 401       |
      |/ocs/v2.php/cloud/users                                     | 997      | 401       |
      |/ocs/v1.php/config                                          | 100      | 200       |
      |/ocs/v2.php/config                                          | 200      | 200       |
      |/ocs/v1.php/privatedata/getattribute                        | 100      | 200       |
      |/ocs/v2.php/privatedata/getattribute                        | 200      | 200       |

  Scenario Outline: using OCS with an app password of a normal user
    Given a new browser session for "user0" has been started
    And the user has generated a new app password named "my-client"
    When the user requests "<endpoint>" with "GET" using the generated app password
    Then the OCS status code should be "<ocs-code>"
    And the HTTP status code should be "<http-code>"
    Examples:
      |endpoint                                                    | ocs-code | http-code |
      |/ocs/v1.php/apps/files_external/api/v1/mounts               | 100      | 200       |
      |/ocs/v2.php/apps/files_external/api/v1/mounts               | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares         | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares         | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/remote_shares/pending | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending | 200      | 200       |
      |/ocs/v1.php/apps/files_sharing/api/v1/shares                | 100      | 200       |
      |/ocs/v2.php/apps/files_sharing/api/v1/shares                | 200      | 200       |
      |/ocs/v1.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v2.php/cloud/apps                                      | 997      | 401       |
      |/ocs/v1.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v2.php/cloud/groups                                    | 997      | 401       |
      |/ocs/v1.php/cloud/users                                     | 997      | 401       |
      |/ocs/v2.php/cloud/users                                     | 997      | 401       |
      |/ocs/v1.php/config                                          | 100      | 200       |
      |/ocs/v2.php/config                                          | 200      | 200       |
      |/ocs/v1.php/privatedata/getattribute                        | 100      | 200       |
      |/ocs/v2.php/privatedata/getattribute                        | 200      | 200       |
