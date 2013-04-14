<?php

$defaultpassword = 'password';

$ldap_edir_con = ldap_connect("ldap://10.5.48.44");
if($ldap_edir_con) $ldap_edir_bind = ldap_bind($ldap_edir_con, 'cn=admin,o=EDIRECTORY', 'eDirP@ssw0rd');

if(!$ldap_edir_bind) die("LDAP1-bind failed...(".ldap_error($ldap_edir_con).")");


$findattrs = array("cn", 'description',  'fullName', 'sn', 'mail', 'givenName', 'title', 'company', 'loginDisabled', 'groupMembership', 'ou');

$search = ldap_search($ldap_edir_con, 'ou=Bruker,ou=Tonsberg,o=VESTFOLD', '(&(objectClass=Person)(!(title=Arkivert))(|(title=Elev)(title=Laerer)(company=IKT)(ou=B)(loginDisabled=true)))', $findattrs);

if(!$search) {
	// Search failed.
	echo "LDAP-errno: ". ldap_errno($ldap_edir_con)."\n";
	echo "LDAP-Error: " . ldap_error($ldap_edir_con)."\n";
}


$ldap = ldap_get_entries($ldap_edir_con, $search);
$output = NULL;


for($i=0;$i<$ldap['count'];$i++) {
	$mail = $ldap[$i]['mail'][0];
	$splitmail = split("@", $mail);
	if(strpos($mail, "nottkom.no")) $kommune = 'Notteroy';
	elseif(strpos($mail, "notteroy.kommune.no")) $kommune = 'Notteroy';
	elseif(strpos($mail, "tbgskole.no")) $kommune = 'Tonsberg';
	elseif(strpos($mail, "tonsberg.kommune.no")) $kommune = 'Tonsberg';
	elseif(strpos($mail, "tbgbhg.no")) $kommune = 'TonsbergBHG';
	



	if($kommune == 'Tonsberg') $maildomain = 'tbgskole.no';
	elseif($kommune == 'Notteroy') $maildomain = 'nottkom.no';
	elseif($kommune == 'TonsbergBHG') $maildomain = 'tbgbhg.no';

	$username = $ldap[$i]['cn'][0]."@".$maildomain;
	
#	$existing_search_attrs = array('cn'. 'zimbraAccountStatus', 'zimbraPrefFromAddress');
#	$search_existing = ldap_search($ldap_zimbra_con, "dc=no",'(&(objectClass=zimbraAccount)(zimbraPrefFromAddress='.$username.'))', $existing_search_attrs);
#	$zimbraldap = ldap_get_entries($ldap_zimbra_con, $search_existing);


	if($ldap[$i]['company'][0] == 'IKT') $policy = 'default'; // IKTpolicy
	elseif($ldap[$i]['title'][0] == 'Laerer') $policy = 'laererpolicy'; // Laererpolicy
	elseif($kommune == 'TonsbergBHG') $policy = 'laererpolicy';
	elseif($ldap[$i]['title'][0] == 'Elev') $policy = 'elevpolicy'; // Elevpolicy
	else $policy = 'default';

	$loginDisabled = $ldap[$i]['logindisabled'][0];
#	print_r($ldap[$i]);
#	if(!empty($loginDisabled)) die();
#	echo "Logindisabled: $loginDisabled";
#	die("Dø");
	if(strpos($mail, $ldap[$i]['cn'][0])) $createalias = 0; // Mail contains username, do not create alias
	else $createalias = 1;

#	if($zimbraldap['count'] == 0) {
	if($loginDisabled == "FALSE" || empty($loginDisabled) ) {
		if($kommune == 'Notteroy') {
			$output .= "da ".$username."\n";
		} else {
#		$output .= "## disabled: ".$ldap[$i]['logindisabled'][0]."\n";
		$output .= "ca ".$username." $defaultpassword\n"; // Create account
		$output .= "ma ".$username." displayName '".fix_charset($ldap[$i]['givenname'][0])." ".fix_charset($ldap[$i]['sn'][0])."'\n";
		$output .= "ma ".$username." zimbraNotes '".date("YmdHi")."'\n";
		$output .= "ma ".$username." description '".$ldap[$i]['company'][0]."'\n";
		$output .= "ma ".$username." sn '".fix_charset($ldap[$i]['sn'][0])."'\n";
		$output .= "ma ".$username." givenName '".fix_charset($ldap[$i]['givenname'][0])."'\n";
		$output .= "ma ".$username." ou '".$ldap[$i]['company'][0]."'\n";
		$output .= "sac ".$username." '".$policy."'\n";
		$output .= "ma ".$username." zimbraAccountStatus active\n";
		$output .= "ma ".$username." zimbraMailStatus enabled\n";
		if($createalias) {
			$output .= "aaa ".$username." ".$splitmail[0]."@".$maildomain."\n";
			$output .= "ma ".$username." zimbraPrefFromAddress ".$splitmail[0]."@".$maildomain."\n";
		}
		if(strpos($mail, "tonsberg.kommune.no")) $output .= "ma ".$username." zimbraMailForwardingAddress '".$mail."'\n";
		if(strpos($mail, "notteroy.kommune.no")) $output .= "ma ".$username." zimbraMailForwardingAddress '".$mail."'\n";

	        
#

		} // End else kommune 

	} // End if !logindisabled

	elseif($loginDisabled == "TRUE") {
		$output .= "ma ".$username." zimbraAccountStatus closed\n";
		$output .= "ma ".$username." zimbraMailStatus disabled\n";
		$output .= "ma ".$username." description '".$ldap[$i]['company'][0]."'\n";


	}
	else $output .= "## Noe rart skjedde...\n";
}

echo $output;

function fix_charset($string) {
        $string = str_replace("Ø", "O", $string);
        $string = str_replace("Å", "A", $string);
        $string = str_replace("Æ", "E", $string);
        $string = str_replace("ø", "o", $string);
        $string = str_replace("æ", "e", $string);
        $string = str_replace("å", "a", $string);

        return $string;

}

