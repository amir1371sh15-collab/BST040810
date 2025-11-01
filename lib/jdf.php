<?php
/*	FarsiWeb, Persian Calendar and Date Conversion Functions
	Copyright (C) 2000-2015 FarsiWeb.info
	Author: Milad Rastian <milad@rastian.com>
	This library is free software; you can redistribute it and/or
	modify it under the terms of the GNU Lesser General Public
	License as published by the Free Software Foundation; either
	version 2.1 of the License, or (at your option) any later version.
	This library is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	Lesser General Public License for more details.
	You should have received a copy of the GNU Lesser General Public
	License along with this library; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/
//---------------------------------
function jdf_div($a,$b)
{
	return (int) ($a / $b);
}
//---------------------------------
function jdf_gregorian_to_jalali($g_y, $g_m, $g_d, $mod='')
{
	$g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	$j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);

	$gy = $g_y-1600;
	$gm = $g_m-1;
	$gd = $g_d-1;

	$g_day_no = 365*$gy+jdf_div($gy+3,4)-jdf_div($gy+99,100)+jdf_div($gy+399,400);

	for ($i=0; $i < $gm; ++$i)
		$g_day_no += $g_days_in_month[$i];
	if ($gm>1 && (($gy%4==0 && $gy%100!=0) || ($gy%400==0)))
		$g_day_no++;
	$g_day_no += $gd;

	$j_day_no = $g_day_no-79;

	$j_np = jdf_div($j_day_no, 12053);
	$j_day_no = $j_day_no % 12053;

	$jy = 979+33*$j_np+4*jdf_div($j_day_no,1461);

	$j_day_no %= 1461;

	if ($j_day_no >= 366)
	{
		$jy += jdf_div($j_day_no-1, 365);
		$j_day_no = ($j_day_no-1)%365;
	}

	for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
		$j_day_no -= $j_days_in_month[$i];
	$jm = $i+1;
	$jd = $j_day_no+1;

	return ($mod=='')?array($jy,$jm,$jd):$jy.$mod.$jm.$mod.$jd;
}
//---------------------------------
function jdf_jalali_to_gregorian($j_y, $j_m, $j_d, $mod='')
{
	$g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
	$j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);

	$jy = $j_y-979;
	$jm = $j_m-1;
	$jd = $j_d-1;

	$j_day_no = 365*$jy + jdf_div($jy, 33)*8 + jdf_div($jy%33+3, 4);
	for ($i=0; $i < $jm; ++$i)
		$j_day_no += $j_days_in_month[$i];

	$j_day_no += $jd;

	$g_day_no = $j_day_no+79;

	$gy = 1600 + 400*jdf_div($g_day_no, 146097);
	$g_day_no = $g_day_no % 146097;

	$leap = true;
	if ($g_day_no >= 36525)
	{
		$g_day_no--;
		$gy += 100*jdf_div($g_day_no,  36524);
		$g_day_no = $g_day_no % 36524;

		if ($g_day_no >= 365)
			$g_day_no++;
		else
			$leap = false;
	}

	$gy += 4*jdf_div($g_day_no, 1461);
	$g_day_no %= 1461;

	if ($g_day_no >= 366)
	{
		$leap = false;
		$g_day_no--;
		$gy += jdf_div($g_day_no, 365);
		$g_day_no = $g_day_no % 365;
	}

	for ($i = 0; $g_day_no >= $g_days_in_month[$i] + ($i == 1 && $leap); $i++)
		$g_day_no -= $g_days_in_month[$i] + ($i == 1 && $leap);
	$gm = $i+1;
	$gd = $g_day_no+1;

	return ($mod=='')?array($gy,$gm,$gd):$gy.$mod.$gm.$mod.$gd;
}
?>

