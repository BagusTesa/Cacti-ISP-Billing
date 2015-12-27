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
#  post_process_playtpus.pl - Script to process exported CSV file into format #
#                             for import in to Playtpus Billing System.       #
#                                                                             #
###############################################################################
#                                                                             #
#  Revision History                                                           #
#                                                                             #
#  Tony Roman - 2007-12-06                                                    #
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
my $output_file = "export_platypus_example.csv";

# Output header row 1 = yes, 0 = no
my $output_header = 1;

# Output delimiter, tab = "\t", use "" for fixed width
my $output_delimiter = ",";

# Output filter - Use this to filter certain rows to output file.
#                 Regular expressions are supported in the "search_expresssion" 
#                 field.  Operators are simply: "and" or "or", more complex 
#                 searches can be supported with a combination of regular 
#                 expression matches and operator combinations.
my $output_filter = {
	operator => "and",
	search => [
		{
			input_field_name => "RateType",
			search_expression => "^Overage\$"
		}
	]
};

# Output format - array of format hashes, available data variables are the same 
#                 as the column names defined in the export CSV file header.
#                 Variable format is simply: "<<GraphTitle>>" or 
#                 "<<CustomerDescription>>".
my $output_format = [
	{
		name => "Account #",
		data => "<<CustomerExternalId>>",
		quoted => 0,
		max_length => -1,
		fixed_length => -1,
		format => "%s"
	},
	{
		name => "Total",
		data => "<<Total>>",
		quoted => 0,
		max_length => -1,
		fixed_length => -1,
		format => "%.2f"
	},
	{
		name => "Decription",
		data => "Bandwidth Overage - <<GraphTitle>>",
		quoted => 1,
		max_length => -1,
		fixed_length => -1,
		format => "%s"
	},
	{
		name => "Invoice Item Identifer",
		data => "BANDOVER",
		quoted => 1,
		max_length => -1,
		fixed_length => -1,
		format => "%s"
	}
];


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



# Process file 
if ($output_header) {
	# Process header
	print FH output_header($output_format, $output_delimiter) . "\n";
}
foreach (@{$data}) {
	if (filter_check($output_filter, $_)) {
		print FH output_row($output_format, $_, $output_delimiter) . "\n";
	}
}

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

# output_row - Output formatted row
#   @arg - $hash - Format hash elements
#   @arg - $data - Data record
#   @arg - $delimiter - Field delimiter string
#   @return - formatted string
sub output_row {

	my $hash = $_[0];
	my $data = $_[1];
	my $delimiter = $_[2];
	my $output = "";

	if (! defined($delimiter)) {
		$delimiter = "";
	}
	if (! defined($hash)) {
		return "";
	}	
	if (! defined($data)) {
		return "";
	}	
	if (ref($hash) ne "ARRAY") {
		return "";
	}
	if (ref($data) ne "HASH") {
		return "";
	}

	foreach (@{$hash}) {
		if ($_->{"quoted"}) {
			$output .= "\"";
		}
		$output .= string_length(sprintf($_->{"format"}, string_var_replace($data, $_->{"data"})), $_->{"max_length"}, $_->{"fixed_length"});
		if ($_->{"quoted"}) {
			$output .= "\"";
		}
		$output .= $delimiter;
	}

	$output =~ s/$delimiter$//;

	return $output; 

}



# output_header - Output formatted header
#   @arg - $hash - Format hash elements
#   @arg - $delimiter - Field delimiter string
#   @return - formatted string
sub output_header {

	my $hash = $_[0];
	my $delimiter = $_[1];
	my $output = "";

	if (! defined($delimiter)) {
		$delimiter = "";
	}
	if (! defined($hash)) {
		return "";
	}	
	if (ref($hash) ne "ARRAY") {
		return "";
	}

	foreach (@{$hash}) {
		if ($_->{"quoted"}) {
			$output .= "\"";
		}
		$output .= string_length($_->{"name"}, $_->{"max_length"}, $_->{"fixed_length"});
		if ($_->{"quoted"}) {
			$output .= "\"";
		}
		$output .= $delimiter;
	}

	$output =~ s/$delimiter$//;

	return $output; 

}


# fitler_check - Check if row matches filter
#  @arg - $filter - Filter hash
#  @arg - $data - Data record
#  @rerturn - 1 = Match, undef = No match
sub filter_check {

	my $filter = $_[0];
	my $data = $_[1];
	my $count = 0;

	if (! defined($filter->{"search"})) {
		return 1;
	}
	if (! defined($filter->{"operator"})) {
		return 1;
	}

	if (scalar(@{$filter->{"search"}}) > 0) {
		foreach (@{$filter->{"search"}}) {
			if ((defined($_->{"input_field_name"})) && (defined($_->{"search_expression"}))) {
				if (defined($data->{$_->{"input_field_name"}})) {
					if ($data->{$_->{"input_field_name"}} =~ /$_->{"search_expression"}/) {
						$count++;
					}
				}
			}
		}
	}else{
		return 1;
	}

	if ($filter->{"operator"} ne "and") {
		if ($count > 0) {
			return 1;
		}
	}else{
		if ($count == scalar(@{$filter->{"search"}})) {
			return 1;
		}
	}

	return 0;

}

sub string_var_replace {

	my $input = $_[0];
	my $string = $_[1];

	if (! defined($input)) {
		return "";
	}
	if (! defined($string)) {
		return "";
	}
	if (ref($input) ne "HASH") {
		return $string;
	}

	foreach (keys(%{$input})) {
		$string =~ s/\<\<$_\>\>/$input->{$_}/g;
	}

	return $string;

}

# string_length - Format a strings lenght
#   @arg - $intput - Input string
#   @arg - $max - Max length of string, -1 = Unlimited
#   @arg - $fixed - Fixed length of string, -1 = No padding
#   @return - formatted string
sub string_length {

	my $input = $_[0];
	my $max = $_[1];
	my $fixed = $_[2];
	my $output = "";
	my $i;

	if (! defined($max)) {
		$max = -1;
	}
	if (! defined($fixed)) {
		$fixed = -1;
	}
	if ($fixed > $max) {
		$fixed = $max;
	}

	if ($max != -1) {
		if (length($input) > $max) {
			for ($i = 0; $i <= $max; $i++) {
				$output .= substr($input, $i, 1);
			}
		} else {
			$output = $input;
		}
	} else {
		$output = $input;
	}

	if ($fixed != -1) {
		if (length($output) < $fixed) {
			$output .= " " x ($fixed - length($output));
		}
	}

	return $output;

}



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
