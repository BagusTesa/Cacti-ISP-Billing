#!/usr/bin/perl -w
###############################################################################
# +-------------------------------------------------------------------------+ #
# | Copyright (C) 2006-2010 Cacti Group                                     | #
# |                                                                         | #
# | This program is free software; you can redistribute it and/or           | #
# | modify it under the terms of the GNU General Public License             | #
# | as published by the Free Software Foundation; either version 2          | #
# | of the License, or (at your option) any later version.                  | #
# |                                                                         | #
# | This program is distributed in the hope that it will be useful,         | #
# | but WITHOUT ANY WARRANTY; without even the implied warranty of          | #
# | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           | #
# | GNU General Public License for more details.                            | #
# +-------------------------------------------------------------------------+ #
# | cacti: a php-based graphing solution                                    | #
# +-------------------------------------------------------------------------+ # 
# | - Cacti - http://www.cacti.net/                                         | #
# +-------------------------------------------------------------------------+ #
###############################################################################
#                                                                             #
#  post_process_xml.pl - Script to process exported CSV file into XML format. #
#                                                                             #
###############################################################################
#                                                                             #
#  Revision History                                                           #
#                                                                             #
#  Tony Roman - 2008-01-17                                                    #
#    Script creation                                                          #
#                                                                             #
###############################################################################

###############################################################################
###############################################################################
#########################                        ##############################
#########################  Output Configuration  ##############################
#########################                        ##############################
###############################################################################
###############################################################################

# File to output the translated file to
my $output_file = "export.xml";

###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
##############                                                #################
##############   NO NEED TO CHANGE ANYTHING AFTER THIS BLOCK  #################
##############                                                #################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################
###############################################################################

# Module Declarations
use strict;
use Data::Dumper;

# Variable Declarations
my $data;
my $customer_prev = "";
my $graph_prev = "";
my $graph_item_prev = "";

# Check command line input
if (! defined($ARGV[0])) {
	print STDERR "ERROR: Invalid syntax\n";
	exit 1;
}

# Load CSV data
$data = load_csv_file($ARGV[0]);
if (! defined($data)) {
	print STDERR "ERROR: " . $@ . "\n";
	exit 1;
}

# Open output file
if (! open(FH, ">" . $output_file)) {
	print STDERR "Unable to open file for write: " . $output_file . "\n";
	exit 1;
}

# Create XML body
print FH "<cacti_isp_billing>\n";

# Process file 
foreach (@{$data}) {

	# Customer block
	if ($customer_prev ne $_->{"CustomerDescription"}) {
		if ($customer_prev ne "") {
			print FH "        </items>\n";
			print FH "      </graph>\n";
			print FH "    </graphs>\n";
			print FH "  </customer>\n";
		}
		print FH "  <customer>\n";
		print FH "    <description>" . xml_encode($_->{"CustomerDescription"}) . "</description>\n";
		print FH "    <external_id>" . xml_encode($_->{"CustomerExternalId"}) . "</external_id>\n";
		print FH "    <billing_interval>\n";
		print FH "      <period_start>" . xml_encode($_->{"BillingPeriodStart"}) . "</period_start>\n";
		print FH "      <period_end>" . xml_encode($_->{"BillingPeriodEnd"}) . "</period_end>\n";
		print FH "      <interval>" . xml_encode($_->{"Interval"}) . "</interval>\n";
		print FH "      <every>" . xml_encode($_->{"Every"}) . "</every>\n";
		print FH "    </billing_interval>\n";
		print FH "    <graphs>\n";
	} 

	# Graph block
	if ($graph_prev ne $_->{"GraphId"}) {
		if ($graph_prev ne "") {
			print FH "        </items>\n";
			print FH "      </graph>\n";
		}
		print FH "      <graph>\n";
		print FH "        <id>" . xml_encode($_->{"GraphId"}) . "</id>\n";
		print FH "        <external_id>" . xml_encode($_->{"GraphExternalId"}) . "</external_id>\n";
		print FH "        <title>" . xml_encode($_->{"GraphTitle"}) . "</title>\n";
		print FH "        <items>\n";
	}

	# Graph item block
	print FH "          <item>\n";
	print FH "            <id>" . xml_encode($_->{"GraphItemId"}) . "</id>\n";
	print FH "            <rate_type>" . xml_encode($_->{"RateType"}) . "</rate_type>\n";
	print FH "            <rate_unit>" . xml_encode($_->{"RateUnit"}) . "</rate_unit>\n";
	print FH "            <rate_amount>" . xml_encode($_->{"RateAmount"}) . "</rate_amount>\n";
	print FH "            <type>" . xml_encode($_->{"Type"}) . "</type>\n";
	print FH "            <bits>" . xml_encode($_->{"Bits"}) . "</bits>\n";
	print FH "            <total>" . xml_encode($_->{"Total"}) . "</total>\n";
	print FH "          </item>\n";

	$graph_prev = $_->{"GraphId"};
	$customer_prev = $_->{"CustomerDescription"};

}

# Close last customer entry
print FH "        </items>\n";
print FH "      </graph>\n";
print FH "    </graphs>\n";
print FH "  </customer>\n";

# Close XML body
print FH "</cacti_isp_billing>\n";

# Close output file
close(FH);

exit 0;


###############################################################################
###############################################################################
##########################                      ###############################
##########################  Functions and Subs  ###############################
##########################                      ###############################
###############################################################################
###############################################################################

# load_csv_file - Load ISP Billing CSV file into memory
#   @arg - $file - File to load
#   @return - Array of hashes containing file data
sub load_csv_file {

	my $data = ();
	my $count = 0;
	my $pos = 0;
	my @headers;
	my $value;
	my $item;
	
	# Open CSV file
	if (! open(FH, $_[0])) {
		$@ = "Unable to open file: " . $_[0];
		return undef;
	}

	# Process CVS file
	foreach (<FH>) {
		chomp();
		$_ =~ s/(^\"|\"$)//g;
		if ($count == 0) {
			# Process header row
			@headers = split("\",\"",$_);
		}else{
			# Process data rows
			$pos = 0;
			$item = undef;
			foreach $value (split("\",\"",$_)) {
				$item->{$headers[$pos]} = $value;	
				$pos++;
			}
			push(@{$data}, $item);
		}
		$count++;
	}

	# Close file
	close(FH);

	# Return data
	if (scalar($data) == 0) {
		$@ = "No data found";
		return undef;
	}
	return $data;
		
}


sub xml_encode {

	my $string = $_[0];

	$string =~ s/\&/\&amp\;/g;
	$string =~ s/\>/\&lt\;/g;
	$string =~ s/\</\&gt\;/g;
	$string =~ s/\'/\&apos\;/g;
	$string =~ s/\"/\&quote\;/g;

	return $string;


}


