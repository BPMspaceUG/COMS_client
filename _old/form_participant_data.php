<?php 


//DB api access
	function api($method, $data) {
		$url = "URL";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_URL, $url);
		$headers = array();
		//JWT token for Authentication
		/************** change following line **********************/
		$headers[] = 'Cookie: token=Token' ;
		if ($data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			$headers[] = 'Content-Type: application/json';
			$headers[] = 'Content-Length: ' . strlen($data);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		return curl_exec($ch);
	}

	function date_compare($a, $b)
	{
		return strtotime($a["coms_exam_event_start_date"]) - strtotime($b["coms_exam_event_start_date"]);
	}

	function less_than ($a, $b){
		return $a < $b;
	}
	function more_than ($a, $b){
		return $a > $b;
	}

	//create bootstrap tables
	function table($data, $headline, $fields, $time=false, $sort="less_than")
	{
		echo "<thead><tr>";
		for ($a=0; $a < count($headline); $a++) { 
			echo "<th>$headline[$a]</th>";
		}

		echo "</tr>
		</thead>
		<tbody>";
		for ($i=0; $i < count($data); $i++) {
			if ($time != false){
				if ($sort(time()-strtotime($data[$i][$time]),0)) {

					echo "<tr>";

					for ($a=0; $a < count($fields); $a++) { 
						echo "<td>".$data[$i][$fields[$a]]."</td>";
					}
					echo "</tr>";
				}
			}else{
				echo "<tr>";
				for ($a=0; $a < count($fields); $a++) { 
					echo "<td>".$data[$i][$fields[$a]]."</td>";
				}
				echo "</tr>";
			}
		}
		echo "</tbody>";
	}

	//load Data from DB
	function loadData(&$booked_exams, &$certificates, &$participant, $PARTID){

		$booked_exams = json_decode(api('POST', json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_coms_participant__Exam_Event", "where" => "coms_participant_id = '$PARTID'")))), true);
		$certificates = json_decode(api('POST', json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_certificate_participant", "where" => "coms_participant_id = '$PARTID' && coms_certificate_type_id != 6 && (state = 'issued' || state = 'revoked')")))), true);
		$participant = json_decode(api('POST', json_encode(array("cmd"=>"read", "paramJS" => array("table" => "coms_participant", "where" => "coms_participant_id = '$PARTID'")))), true);
		usort($booked_exams, 'date_compare');
		
	}


	$actionurl = $main_domain . $canonical ."/" . $PARTID_MD5 . "/" . $PARTID . "/";
	$success = false;
	$showerror = false;
	$login = false;
	$BACKBUTTON = False;
	// Parameter
	@$gender = htmlspecialchars($_POST['gender']);
	@$ICO_matr_last3 = htmlspecialchars($_POST['ICO_matr_last3']);
	@$firstname = htmlspecialchars($_POST['firstname']);
	@$lastname = htmlspecialchars($_POST['lastname']);
	@$dateofbirth = date_create_from_format('j.m.Y', htmlspecialchars($_POST['dateofbirth']));
	@$placeofbirth = htmlspecialchars($_POST['placeofbirth']);
	@$birthcountry = htmlspecialchars($_POST['birthcountry']);
	@$submit = htmlspecialchars($_POST['submit']);
	
	if (isset($_POST['language'])){
		api('POST', json_encode(array("cmd"=>"update", "paramJS" => array("table" => "coms_participant", "row" => array("coms_participant_id" => $PARTID, "coms_participant_language_id" => $_POST['language_id'])))));
		$language=$_POST['language'];
	} 
	if (isset($_POST['dateofbirth']) && $_POST['dateofbirth'] != "") {
		$dateofbirth = date_format($dateofbirth, 'Y-m-d');
	}
	@$data = array(
		htmlspecialchars($_POST['gender']),
		//htmlspecialchars($_POST['ICO_matr_last3']),
		//htmlspecialchars($_POST['firstname']),
		//htmlspecialchars($_POST['lastname']),
		$dateofbirth,
		htmlspecialchars($_POST['placeofbirth']),
		htmlspecialchars($_POST['birthcountry']),
	);
	@$sql_data = array(
		"coms_participant_gender",
		//"coms_participant_id",
		//"coms_participant_firstname",
		//"coms_participant_lastname",
		"coms_participant_dateofbirth",
		"coms_participant_placeofbirth",
		"coms_participant_birthcountry",
	);
	$sql = array();
	$sql_api = array("coms_participant_id" => $PARTID);
	$sql_data_api = array();
	if (isset($_GET["download"])) {	
		$booked_exams = array();
		$certificates = array();
		$participant = array();
							//only load data when matriculation number was correct
							//pass data arrays to function
		loadData($booked_exams, $certificates, $participant, $PARTID);
		if ($participant[0]['state_id'] == 111 && $certificates[$_GET['download']]['state'] != 'revoked'){
		$certpath = "../../certificates/";
		$certificateFileName = $PARTID."_".$participant[0]["coms_participant_lastname"]."_".$participant[0]["coms_participant_firstname"]."/".$certificates[$_GET['download']]["Certificate"];
		$pdfcontent = api('POST', json_encode(array("cmd" =>"getFile", "paramJS" => array("name" => $certificateFileName, "path" => $certpath))));
		$file = $certificates[$_GET['download']]["coms_certificate_participant_id_base32"].".pdf";
		//file_put_contents($file, $pdfcontent);
		
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.basename($file).'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			//header('Content-Length: ' . filesize($file));
			ob_end_clean();
			echo $pdfcontent;
			ob_end_clean();
			}
			exit;
		
	}else if (!$login && isset($_GET["download"]) ){
		echo "Got ya";
	}
	if (ctype_xdigit($PARTID_MD5) && strlen($PARTID_MD5) == 32 && strlen($PARTID) == 6) {
				// MatrNr Postfix calculate
		$PARTID_MD5_first5 = substr($PARTID_MD5, 0, 5);
		$matNr_postfix = substr(base_convert($PARTID_MD5_first5, 16, 10), 0, 3);
				//echo "PostFIX MatrkNr = ".$matNr_postfix."<br/>";
		$matNr = $PARTID.substr(base_convert($PARTID_MD5_first5, 16, 10), 0, 3);
		$matNr = str_pad(strtoupper(base_convert($matNr, 10, 32)), 8, "0", STR_PAD_LEFT);
				//echo $actionurl;
	}
	else {
		$invalid = true;
	}
	for ($i = 0; $i < count($sql_data); $i++) { 
		if ($data[$i] != null) {
			$sql_api[$sql_data[$i]] = "$data[$i]";
		}
	}
	// Show error if something was submitted
	if (strlen($submit) > 0) {
		$showerror = true;
	}
	//"login"
	if (isset($_POST['ICO_matr_last3'])) {
		if ($_POST['ICO_matr_last3'] == $matNr_postfix){
			$PartState = json_decode(api('POST', json_encode(array("cmd"=>"read", "paramJS" => array("table" => "coms_participant", "select" => "state_id" ,"where" => "coms_participant_id = '$PARTID'")))), true);
			//var_dump($PartState);
			if ($PartState[0]["state_id"] != 112){
			$showerror = false;
			$login = true;
		}
		}else{
			$showerror = true;
			$login = false;
		}
	}
	//write updated personal data to DB
	if ($gender != null || $dateofbirth != null || $placeofbirth != null || $birthcountry != null) {
		$showerror = false;
		$success = true;
		$_POST['PartID'] = $PARTID;
		$zwischenvar = json_encode(array("cmd"=>"update", "paramJS" => array("table" => "coms_participant", "row" => $sql_api)));
		//var_dump($zwischenvar);
		$errr = api('POST', $zwischenvar);
		api('POST', json_encode(array("cmd" => "makeTransition",
            "paramJS" => array("table" => "coms_participant", "row"=>array("coms_participant_id"=> $PARTID, "state_id" => 111)))));
		//var_dump($errr);
	}
	?>


	<div id="form_participant_data" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="form_participant_data" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<div class="row">
						<div class="col-md-2">
							<a href="/"><img class="img-responsive" style="margin: 0 auto;" src="/img/IMG180px.png" alt="Logo" title="Logo"></a>
						</div>
						<div class="col-md-8">
							<div class="headline">
								<h2 class="modal-title" id="form_participant_dataLable">
									<?php echo $RP->replace($RP,'form_participant_data_headline',$language); ?>
								</h2>
							</div>
						</div>
						<div class="col-md-2 text-right">
							<?php if($BACKBUTTON) echo "<a onclick=\"goBack()\" data-toggle=\"tooltip\" title=\"back\" href=\"\"> <i class=\"fa fa-arrow-circle-o-left fa-2x\" aria-hidden=\"true\"></i></a>&nbsp;"?><a href="<?php
							if ($EN)
							echo "/en";
							if ($DE)
							echo "/de";
							?>" role="button" data-toggle="tooltip" title="home"><i class="fa fa-times-circle-o fa-2x" aria-hidden="true"></i></a>
						</div>
					</div>
				</div>
				<div class="md-margin-bottom-40"></div>
				<div class="modal-body">

					<div style="width: 85%; margin: 0 auto;">
						<?php
						if ($showerror)
							echo '<div class="alert alert-danger" role="alert">
						<span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Missing or false input:<ul><li> Please enter the last three digits of your matriculation number.</li><ul></div>';
						if ((!isset($bookingpw) && !$login)|| $showerror) {
							?>
							<!-- Show password input first-->
							<form class="form-horizontal" method="post" action="<?php $actionurl ?>">
								<div class="form-group row">
									<?php echo $RP->replace($RP,'form_participant_data_matriculation_number',$language); ?>
								</div>

								<div class="form-group">
									<label for="inputPassword" class="col-sm-2 control-label"></label>
									<div class="col-sm-6">
										<input type="submit" class="btn btn-primary" name="submit" value="&#8594; Weiter">

									</div> 



								</div>
							</form>
							<!--End of password input -->


							<?php
						}else if (!$showerror){
							$booked_exams = array();
							$certificates = array();
							$participant = array();
							//only load data when matriculation number was correct
							//pass data arrays to function
							loadData($booked_exams, $certificates, $participant, $PARTID);
							if ($participant[0]['state_id'] == 110) {
								foreach ($booked_exams as $key => $value) {
									if ($booked_exams[$key]['coms_participant_info'] != ""){
										$booked_exams[$key]['coms_participant_info'] = "Your personal data still is incomplete, to get your results please complete your personal data";
										$booked_exams[$key]['participant_state_name'] = '';
									}
								}
							}
							foreach ($certificates as $key => $value) {
								if ($certificates[$key]["state"] == "revoked" || $participant[0]['state_id'] != 111) {
									$certificates[$key]["download"] = "";
								}else{
								$certificates[$key]["download"] = "<a href='?download=".$key."'>Download</a> ";
								//var_dump($value);
							}
							}
							//var_dump($certificates);
							
							if ($success) {
								echo $RP->replace($RP,'form_participant_data_submit_success',$language); 
							}
							if ($showerror) {
								echo $RP->replace($RP,'form_participant_data_submit_error',$language); 
							}
							?>

							


							<ul class="nav nav-tabs">
								<li class="active">
									<a data-toggle="tab" href="#personalData">Personal data</a>
									<li>
										<a data-toggle="tab" href="#events">Events</a>
									</li>
									<li>
										<a data-toggle="tab" href="#certificates">Certificates</a>
									</li>
								</ul>

								<div class="tab-content">
									<!--table for to display certificates-->
									<div id="certificates" class="tab-pane fade">
										<h3>Certificates</h3>
										<table class="table table-hover">
											<?php
											table($certificates, array("ID", "Download", "Exam name", "State"),array("coms_certificate_participant_id", "download", "coms_certificate_name", "state"));
											?>
										</table>
									</div>
									<div id="personalData" class="tab-pane fade in active">
										<h3>Personal data</h3>
										<!--form for personal data-->
										<form class="form-horizontal" method="post" action="<?php $actionurl ?>">


											<div class="form-group">
												<h5 style="font-weight: bold;">Your personal data right now:</h5>
												<table class="table">
													<?php
													table($participant, array("Firstname", "Lastname", "Gender", "Birthday", "Birthplace", "Birthcountry"),array("coms_participant_firstname", "coms_participant_lastname", "coms_participant_gender", "coms_participant_dateofbirth", "coms_participant_placeofbirth", "coms_participant_birthcountry"));
													?>
												</table>
											</div>

											<?php switch ($language) {
								case 'en':
									?>
									<form class="form-horizontal" method="post" action="<?php $actionurl ?>">
 									<div hidden>
										<input type="text" name="ICO_matr_last3" value="<?php echo $ICO_matr_last3;?>">
									</div>
									<div hidden>
										<input type="text" name="language" value="de">
									</div>
									<div hidden>
										<input type="text" name="language_id" value="5">
									</div>
									<div class="form-group">
										<label for="inputPassword" class="col-sm-6 control-label"></label>
										<div class="col-sm-6">
											<input type="submit" class="btn btn-primary" name="change language" value="Change language to german">
										</div>
									</div>
									<?php
									break;
									case 'de':
									?>
									<form class="form-horizontal" method="post" action="<?php $actionurl ?>">
 									<div hidden>
										<input type="text" name="ICO_matr_last3" value="<?php echo $ICO_matr_last3;?>">
									</div>
									<div hidden>
										<input type="text" name="language" value="en">
									</div>
									<div hidden>
										<input type="text" name="language_id" value="6">
									</div>
									<div class="form-group">
										<label for="inputPassword" class="col-sm-6 control-label"></label>
										<div class="col-sm-6">
											<input type="submit" class="btn btn-primary" name="change language" value="Change language to english">
										</div>
									</div>
									<?php
									break;
								
								default:
									# code...
									break;
							}
									 
											if ($participant[0]['state_id'] == 110) {
												?>

												<div class="alert alert-warning"><i class="fa fa-pencil" aria-hidden="true"></i>
													<?php echo $RP->replace($RP,'form_participant_data_info2',$language); ?></div>
													<div id="textboxes" >

														<div class="form-group">
															<?php echo $RP->replace($RP,'form_participant_data_gender',$language); ?>
														</div>

														<div class="form-group">
															<?php echo $RP->replace($RP,'form_participant_data_birthday',$language); ?>
														</div>

														<div class="form-group">
															<?php echo $RP->replace($RP,'form_participant_data_birth_place',$language); ?>
														</div>
														<div class="form-group">
															<?php echo $RP->replace($RP,'form_participant_data_birth_country',$language); ?>
														</div>
														<div hidden>
															<input type="text" name="ICO_matr_last3" value="<?php echo $ICO_matr_last3;?>">
														</div>
													</div>

													<div class="form-group">
														<label for="inputPassword" class="col-sm-6 control-label"></label>
														<div class="col-sm-6">
															<input type="submit" class="btn btn-primary" name="submit" value="&rarr; Update">
														</div>
													</div>
												</form>
												<?php
											}
											?>
											<?php echo $RP->replace($RP,'form_participant_data_confidentiality',$language); ?>

											<?php echo $RP->replace($RP,'form_participant_data_examination_regulations',$language); ?>

											<?php echo $RP->replace($RP,'form_participant_data_data_protection',$language); ?>


										</div>


										<!--table for to display booked events-->
										<div id="events" class="tab-pane fade">
											<ul class="nav nav-tabs">
												<li class="active">
													<a data-toggle="tab" href="#upcoming">Upcoming</a>
												</li>
												<li>
													<a data-toggle="tab" href="#past">Past</a>
												</li>
											</ul>

											<div class="tab-content">
												<div id="upcoming" class="tab-pane fade in active">
													<h3 style="margin-top: 25px;">Upcoming events</h3>
													<table class="table table-hover">
														<?php
														table($booked_exams, array("Event ID",
															"Event name",
															"Start date"),
														array("coms_exam_event_id",
															"coms_exam_event_name",
															"coms_exam_event_start_date"),
														"coms_exam_event_start_date", "less_than"
													)
													?>
												</table>
											</div>


											<div id="past" class="tab-pane fade">
												<h3 style="margin-top: 25px;">Past events</h3>
												<table class="table table-hover">
													<?php
													table($booked_exams, array("Event ID",
														"Event name",
														"Percentage",
														"Start date",
														"State"),
													array("coms_exam_event_id",
														"coms_exam_event_name",
														"coms_participant_info",
														"coms_exam_event_start_date",
														"participant_state_name"),
													"coms_exam_event_start_date", "more_than"
												)
												?>
											</table>
										</div>
									</div>
								</div>
							</div>


						<?php } ?>

					</div>		
				</div>



				<div class="modal-footer">
					<?php 
					if($BACKBUTTON) {
						echo "<button class=\"btn btn-secondary\" onclick=\"goBack()\" data-toggle=\"tooltip\" title=\"back\"> <i class=\"fa fa-arrow-circle-o-left fa-2x\" aria-hidden=\"true\"></i>";
						if ($EN) echo " back";
						if ($DE) echo " zurück";
						echo "</button>";}
						?>
						<a class="btn btn-primary" href="<?php
						if ($EN)
						echo "/en";
						if ($DE)
						echo "/de";
						?>" role="button" data-toggle="tooltip" title="home"><i class="fa fa-times-circle-o" aria-hidden="true"></i>&nbsp;<?php
						if ($EN)
							echo "Home";
						if ($DE)
							echo "Home";
						?></a>
					</div>
				</div>
			</div>
		</div>

</div>
