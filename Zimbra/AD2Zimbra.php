<?php

$defaultpassword = 'password'; // This is the default password assigned to Zimbra users if no LDAP authentication configured
$userdomain = 'example.net'; // This is the userdomain ( username@<userdomain>)
$ldap_server = 'ldap://10.42.1.5';
$ldap_username = 'cn=Zimbra Sync,ou=ServiceAccount,dc=logon,dc=example,dc=net'; // User to connect to LDAP as
$ldap_password = 'ADp@ssw0rd'; // The password for user in ldap_username
$ldap_base_search = 'ou=mailusers,dc=logon,dc=example,dc=net'; // Where to search for users
$policy = 'defaultpolicy';

$ldap_ad_con = ldap_connect($ldap_server);
ldap_set_option($ldap_ad_con, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_ad_con, LDAP_OPT_SIZELIMIT,1000); // Limit the results to 1000 users. Need to use global catalog to get more than 1000
if($ldap_ad_con) $ldap_ad_bind = ldap_bind($ldap_ad_con, $ldap_username, $ldap_password);

if(!$ldap_ad_bind) die("LDAP1-bind failed...(".ldap_error($ldap_ad_con).")");



#$findattrs = array("cn", 'description',  'fullName', 'sn', 'mail', 'givenName', 'title', 'company', 'loginDisabled');
$findattrs = array("cn", "sn", "name", "uid", "sAMAccountName", "displayName", "name", "description", "userAccountControl", "mail");

$search = ldap_search($ldap_ad_con, $ldap_base_search, '(objectClass=User)', $findattrs);

if(!$search) {
	// Search failed.
	echo "LDAP-errno: ". ldap_errno($ldap_ad_con)."\n";
	echo "LDAP-Error: " . ldap_error($ldap_ad_con)."\n";
}


$ldap = ldap_get_entries($ldap_ad_con, $search);
$output = NULL;


for($i=0;$i<$ldap['count'];$i++) {
	$mail = $ldap[$i]['mail'][0];
	$splitmail = split("@", $mail);
	if($splitmail[1] == "externalusers.com") $dontCreate = TRUE;
	else $dontCreate = FALSE;


	$username = $ldap[$i]['samaccountname'][0]."@".$userdomain;
	$UAC = $ldap[$i]['useraccountcontrol'][0];
#	echo $UAC;
	if($UAC == 512) $loginDisabled = FALSE;
	elseif($UAC == 514) $loginDisabled = TRUE;
	elseif($UAC == 66050) $loginDisabled = TRUE; // Account disabled + password never expire
	elseif($UAC == 66048) $loginDisabled = FALSE; // PWD never expire + can't change password
	elseif($UAC == 530) $loginDisabled = TRUE; // Account disabled + change password next logon
	else die("Unknown statuscode $UAC on $username ".$ldap[$i]['cn'][0]); // check code on http://support.microsoft.com/kb/305144 and add

	
	
	if(strpos($mail, $ldap[$i]['cn'][0])) $createalias = 0; // Mail contains username, do not create alias
	else $createalias = 1;

#	if($zimbraldap['count'] == 0) {
	if($loginDisabled == FALSE && $dontCreate == FALSE) {
#		$output .= "## disabled: ".$ldap[$i]['logindisabled'][0]."\n";
		$output .= "ca ".$username." $defaultpassword\n"; // Create account
		$output .= "ma ".$username." displayName '".$ldap[$i]['displayname'][0]."'\n";
		$output .= "ma ".$username." zimbraNotes '".date("YmdHi")."'\n";
		$output .= "ma ".$username." description '".$ldap[$i]['description'][0]."'\n";
#		$output .= "ma ".$username." sn '".$ldap[$i]['sn'][0]."'\n";
#		$output .= "ma ".$username." givenName '".$ldap[$i]['givenname'][0]."'\n";
#		$output .= "ma ".$username." ou '".$ldap[$i]['company'][0]."'\n";
		$output .= "sac ".$username." '".$policy."'\n";
		$output .= "ma ".$username." zimbraAccountStatus active\n";
		$output .= "ma ".$username." zimbraMailStatus enabled\n";
		if($createalias) {
			$output .= "aaa ".$username." ".$splitmail[0]."@".$userdomain."\n";
			$output .= "ma ".$username." zimbraPrefFromAddress ".$splitmail[0]."@".$userdomain."\n";
		}
#		if(strpos($mail, "tonsberg.kommune.no")) $output .= "ma ".$username." zimbraMailForwardingAddress '".$mail."'\n";
#		$output .= "ma ".$username." zimbraPrefLocale no\n"; // Set default language to norwegian

#	$output .= "aal -s SADM-MAIL02.ped.local ".$username." zimbra.soap debug\n";
#	echo $ldap[$i]['cn'][0]."\n";
	} // End if !logindisabled

	elseif($loginDisabled == "TRUE") {
		$output .= "ma ".$username." zimbraAccountStatus closed\n";
		$output .= "ma ".$username." zimbraMailStatus disabled\n";
		$output .= "ma ".$username." description '".$ldap[$i]['description'][0]."'\n";


	}
#	else $output .= "## Noe rart skjedde...\n";
}

echo $output;
