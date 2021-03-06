	<!-- Modal  -->


<?php

$actionurl = $main_domain . $canonical ."/" . $PARTID_MD5 . "/" . $PARTID . "/";

function api($method, $data) {
    $url = "Your domain here";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_URL, $url);
    $headers = array();
        //JWT token for Authentication
    /************** change following line **********************/
    $headers[] = 'Cookie: token=Your token here' ;
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
                echo "</tr>";}
                
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

    function loadData(&$exams, &$trainer, &$proctor, &$booked_exams, &$trexor, $PARTID){
        $exams = json_decode(api('POST', 
            json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_csvexport_trainingorg_exam", "where" => "coms_training_organisation_id = '$PARTID'")))), true);
        $trainer =  json_decode(api('POST', 
            json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_csvexport_trainingorg_trainer", "where" => "coms_training_organisation_id = '$PARTID'")))), true);
        $proctor = json_decode(api('POST',  
            json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_csvexport_trainingorg_proctor", "where" => "coms_training_organisation_id = '$PARTID'")))), true);
        $booked_exams = json_decode(api('POST', json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_coms_trainingorg_exam_events", "where" => "coms_training_org_id = '$PARTID'")))), true);
        usort($booked_exams, 'date_compare');

        $trid = array();
        for ($i=0; $i <count($trainer) ; $i++) { 
            $trid[] = $trainer[$i]['coms_trainer_id'];
        }

        $exid = array();
        for ($i=0; $i <count($exams); $i++){
            $exid[] = $exams[$i]['coms_exam_id'];
        }
        $tridcsv = implode(",", $trid);
        $exidcsv = implode(",", $exid);
        $trexor = 
            json_decode(api('POST',  json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_csvexport_trainer_exam", "where" => "coms_trainer_id in ($tridcsv) && coms_exam_id in ($exidcsv)")))), true);

    }


    $success = false;
    $showerror = false;
    $invalid = false;
    $submit = NULL;





//check validity of URL
    if (ctype_xdigit($PARTID_MD5) && strlen($PARTID_MD5) == 32 && strlen($PARTID) == 6 && $PARTID_MD5 == (md5($PARTID))) {
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

  // Show error if something was submitted
    if (strlen($submit) > 0) {

        $showerror = true;
    }



//"login"
    if (isset($_POST['booking-pw'])) {
        $bookingpw = hash("sha512",htmlspecialchars($_POST['booking-pw']).$PARTID_MD5);

        $db_content = json_decode(api('POST', 
            json_encode(array("cmd"=>"read", "paramJS" => array("table" => "v_csvexport_trainingorg", "where" => "coms_training_organisation_passwd_hash = '$bookingpw'")))), 
        true);


        if (count($db_content) == 1 && $db_content['0']['coms_training_organisation_id'] == $PARTID){
            $ATO_NAME = $db_content['0']['coms_training_organisation_name'];
            $showerror = false;
            $success = false;

        }else{
            $showerror = true;
            $success = false;
        }
    }





    if (isset($_POST['exam']) && htmlspecialchars($_POST['exam']) != "") {
        $date = implode("-", array_reverse(explode(".", htmlspecialchars($_POST['date']))));
$ATO_NAME = $_POST['ATO_NAME'];
        $errr = api('POST', 
            json_encode(array("cmd"=>"create", "paramJS" => array("table" => "coms_exam_event", "row" => array(
                "coms_exam_id" => htmlspecialchars($_POST['exam']),
                "coms_trainer_id" => htmlspecialchars($_POST['trainer']),
                "coms_training_org_id" => htmlspecialchars($PARTID),
                "coms_proctor_id" => htmlspecialchars($_POST['proctor']),
                "coms_exam_event_start_date" => $date. " " .htmlspecialchars($_POST['start-time']),
                "coms_exam_event_location" => htmlspecialchars($_POST['location']),
                "coms_delivery_type_id" => "5")))));
        $email_message = "Das ATO $ATO_NAME hat ein neues Event gebucht. Hier geht es zu COMS: COMS LINK";
        $header = "From: Email". "\r\n" .
    'X-Mailer: PHP/' . phpversion();
        mail('Email', 'Neue Event Buchung', $email_message, $header);



    $ATO_NAME = $_POST['ATO_NAME'];
    if (strpos($errr, 'DateTime') !== false) {
        $errdate = true;
    }else{
        $showerror = false;
        $success = true;
    }


}






?>

<div id="form_event_data" class="modal fade in" tabindex="-1" role="dialog" aria-labelledby="form_event_data" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="row">
                    <div class="col-md-2">
                        <a href="/"><img class="img-responsive" style="margin: 0 auto;" src="/img/" alt="Logo" title="Logo"></a>
                    </div>
                    <div class="col-md-8">
                        <div class="headline">
                            <h2 class="modal-title" id="form_event_dataLable">
                                <?php
                                if ($EN)
                                    echo "Form ATO";
                                if ($DE)
                                    echo "Formular ATO";
                                ?>
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
            
                <?php

if ($invalid) {

    echo '<div style="width: 85%; margin: 0 auto;">';
    echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Your URL is not valid - please contact us via mail&nbsp;<a href="mailto:Email?subject=Book event">Email</a> if you want to book an exam. </div>';
    echo "</div>";
}else{
    ?>

    <div style="width: 95%; margin: 0 auto;">
        <?php

        
        if ($showerror)
            echo '<div class="alert alert-danger" role="alert">
        <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Missing or false input:<ul><li> Please enter your booking password.</li><ul></div>';

        if ((!isset($bookingpw) && !$success && !isset($errdate))|| $showerror) {



            ?>
            <!-- Show password input first-->
            <form class="form-horizontal" method="post" action="<?php $actionurl ?>">
                <div class="form-group row">
                    <label class="col-sm-2 control-label">Bestellpasswort<span class="text-danger">*</span></label>
                    <div class="col-sm-6">
                        <input  style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" type="password" class="form-control required" name="booking-pw" placeholder="Passwort" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputPassword" class="col-sm-2 control-label"></label>
                    <div class="col-sm-6">
                        <input type="submit" class="btn btn-primary" name="submit" value="&#8594; Proceed">

                    </div> 



                </div>
            </form>
            <!--End of password input -->


            <?php
        }else if (!$showerror){
            $exvalue = "Please select exam...";

            $exams = array();
            $trainer = array();
            $proctor = array();
            $booked_exams = array();
            $trexor = array();

            loadData($exams,$trainer,$proctor,$booked_exams,$trexor, $PARTID);
            ?>
            
            <div class="col-sm-12 text">
                <h1 class="form-control-static">
                    <?php 
                    echo '<h3>';
                    echo $ATO_NAME;
                    echo '</h3>';

                    if ($success) {
                        echo '<div class="alert alert-success" role="alert">
                        <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Thank you for your order! Currently the status of your order is unconfirmed. Please send us an E-Mail in case we do not confirm your order at least within the next 2 working days.</div>';
                    }
                    ?>

                </div>
                <div class="col-sm-12 form-goup row" style="padding-bottom: 10pt;">
                    <button type="button" class="btn btn-primary " data-toggle="modal" data-target="#book_event">&#x271A;&nbsp;Book a new event</button>

                </div>

                <!-- form for booking an exam, after password input-->
                


                <!--End of booking form-->

                <ul class="nav nav-tabs">
                    <li class="active">
                        <a data-toggle="tab" href="#events">Events</a>
                    </li>
                    <li>
                        <a data-toggle="tab" href="#exams">Exams</a>
                    </li>
                    <li>
                        <a data-toggle="tab" href="#trainer">Trainer</a>
                    </li>
                    <li>
                        <a data-toggle="tab" href="#proctor">Proctors</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div id="proctor" class="tab-pane fade">
                        <h3>Registered proctors</h3>
                        <table class="table table-hover">
                            <?php
                            table($proctor, array("ID","Firstname", "Lastname", "State"),array("coms_proctor_id","coms_proctor_firstname", "coms_proctor_lastname", "state"));
                            ?>
                        </table>
                    </div>
                    <div id="exams" class="tab-pane fade">
                        <h3>Registered exams</h3>
                        <table class="table table-hover">
                            <?php
                            table($exams, array("Exam ID", "Exam name", "State"),array("coms_exam_id", "coms_exam_name", "state"));
                            ?>
                        </table>
                    </div>
                    <div id="trainer" class="tab-pane fade">
                        <h3>Registered trainers</h3>
                        <table class="table table-hover">
                            <?php
                            table($trainer, array("ID","Firstname", "Lastname", "State"), array("coms_trainer_id","coms_trainer_firstname", "coms_trainer_lastname", "state"));
                            ?>
                        </table>
                    </div>


                    <div id="events" class="tab-pane fade in active">
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
                                        "Start date",
                                        "Trainer lastname",
                                        "Trainer firstname",
                                        "Proctor lastname",
                                        "Proctor firstname",
                                        "Booking state"),
                                    array("coms_exam_event_id",
                                        "coms_exam_name",
                                        "coms_exam_event_start_date",
                                        "coms_trainer_lastname",
                                        "coms_trainer_firstname",
                                        "coms_proctor_lastname",
                                        "coms_proctor_firstname",
                                        "state"),
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
                                    "Start date",
                                    "Trainer lastname",
                                    "Trainer firstname",
                                    "Proctor lastname",
                                    "Proctor firstname",
                                    "Booking state"),
                                array("coms_exam_event_id",
                                    "coms_exam_name",
                                    "coms_exam_event_start_date",
                                    "coms_trainer_lastname",
                                    "coms_trainer_firstname",
                                    "coms_proctor_lastname",
                                    "coms_proctor_firstname",
                                    "state"),
                                "coms_exam_event_start_date", "more_than"
                            )
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>










    <script>

        var TOID = '<?php echo $PARTID;?>';
        var run2 = false;
        var run3 = false;
        console.log('<?php echo $PARTID;?>');
        document.addEventListener("DOMContentLoaded", step1);
        document.addEventListener("DOMContentLoaded", step4);

        <?php
        if (isset($errdate)){
            echo 'document.addEventListener("DOMContentLoaded", eventModal);';}?>
            function eventModal(){$("#book_event").modal("show");};

        // select exams depending on Training Organisation  
        function step1() {
            var exams = <?php echo (json_encode($exams));?>;
            var $firstChoicee = $("#sel1");
            $.each(exams, function(index, value) {
                $firstChoicee.append("<option value='" + value.coms_exam_id + "'>" + value.coms_exam_name + " - "+ value.language +"</option>");
            });
            var trainer = <?php echo (json_encode($trainer));?>;
            var $firstChoicet = $("#sel2");
            $.each(trainer, function(index, value) {
                $firstChoicet.append("<option value='" + value.coms_trainer_id + "'>" + value.coms_trainer_lastname + " " + value.coms_trainer_firstname + "</option>");
            });
        };   
        

        function ChangedExam() { //  now change exam depending at TO and trainer

            if ($("#sel2").val() == '' || run3 == true) {
                run3 = false;
                var trfilt = new Array();
                
                if($("#sel1").val() == ''){//if reseted to default show default info
                    var trainer = <?php echo (json_encode($trainer));?>;
                    trfilt = trainer;
                }else {
                    var ttr = <?php echo (json_encode($trexor));?>;
                    $.each(ttr, function(index, value){
                        if (value.coms_exam_id == $("#sel1").val()) {
                            trfilt.push(value);} 


                        });
                };
                //set options
                var $secondChoice = $("#sel2");
                $secondChoice.empty();
                $secondChoice.append("<option selected value value=''>Please select...</option>");
                $.each(trfilt, function(index, value) {
                    $secondChoice.append("<option value='" + value.coms_trainer_id + "'>" + value.coms_trainer_lastname + " " + value.coms_trainer_firstname + "</option>");
                });
            }else{
                run2 = true;
            };
        };
        function ChangedTrainer() { //  now change trainers depending at TO and Exams           
            if ($("#sel1").val() == '' || run2 == true){
                run2 = false;
                exfilt = new Array();

                if($("#sel2").val() == ''){//if reseted to default show default info
                    var exams = <?php echo (json_encode($exams));?>;
                    exfilt = exams;
                }else {
                    var tex = <?php echo (json_encode($trexor));?>;
                    $.each(tex, function(index, value){
                        if (value.coms_trainer_id == $("#sel2").val()) {
                            exfilt.push(value);
                        }  
                    });
                };
                //set options
                var $secondChoice = $("#sel1");
                $secondChoice.empty();
                $secondChoice.append("<option selected value value=''>Please select...</option>");
                $.each(exfilt, function(index, value) {
                    $secondChoice.append("<option value='" + value.coms_exam_id + "'>" + value.coms_exam_name + "</option>");
                });
            } else{
                run3 = true;
            };
        };
        
        
        function step4() { //just need to select proctor

            var pctr = <?php echo (json_encode($proctor));?>;
            var $proctorChoice = $("#sel3");
                    //$proctorChoice.empty();
                    $.each(pctr, function(index, value) {
                        $proctorChoice.append("<option value='"+value.coms_proctor_id+"'>" + value.coms_proctor_lastname + " " + value.coms_proctor_firstname + "</option>");
                    });


                };


            </script>

            <?php
        }

        echo "  </div>";
} 
?>      






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
<div class="modal fade" id="book_event" role="dialog">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content" width="80%">
                            <div class="modal-header">
                                <h4 class="modal-title">Book event</h4>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>

                            </div>
                            <div class="modal-body">

                                <form class="form-horizontal" method="post" action="<?php $actionurl ?>">


                                    <div class="form-group row">
                                        <label class="col-sm-2" for="sel1">Topic of the exam:<span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <select style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" class="form-control required" id="sel1" name="exam" required onchange="ChangedExam()">
                                                <option  selected value value=''>Please select</option>

                                            </select>
                                        </div>
                                    </div> 

                                    <div class="form-group row">
                                        <label class="col-sm-2 control-label">Trainer<span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <select style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" class="form-control required" id="sel2" name="trainer" required onchange="ChangedTrainer()">
                                                <option  selected value value=''>Please select</option>

                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-2 control-label">Proctor<span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <select style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" class="form-control required" id="sel3" name="proctor" required>
                                                <option disabled selected value value="">Please select...</option>

                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-2 control-label">Location<span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <input  style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" type="text" class="form-control required" name="location" placeholder="Location" required>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <?php
                                        if (isset($errdate)){
                                            echo '<div style="width: 85%; margin: 0 auto;">';
                                            echo '<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span>Please enter the date in the given format.</div>';
                                            echo "</div>";
                                        }
                                        ?>
                                        <label class="col-sm-2 control-label">Start date<span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <input style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" type="date" pattern="^[0-9]{2}.[0-9]{2}.[0-9]{4}$" required class="form-control  required" name="date" placeholder="dd.mm.yyyy" required>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-2 control-label">Start time<span class="text-danger">*</span></label>
                                        <div class="col-sm-6">
                                            <input style="padding: 5px 10px; border-radius: 2px; border: 1px solid rgb(216, 222, 228);" autocomplete="off" type="time" required class="form-control required" name="start-time" value="15:00" required>
                                        </div>
                                    </div>

                                    <div class="text-danger row">
                                        <label for="inputPassword" class="col-sm-2 control-label"></label>
                                        <p class="col-sm-4 text-danger">* = Mandatory field</p>
                                    </div> 
                                    <div hidden>
                                        <input type="text" name="ATO_NAME" value="<?php echo $ATO_NAME;?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="inputPassword" class="col-sm-2 control-label"></label>
                                        <div class="col-sm-6">
                                            <input type="submit" class="btn btn-primary" name="submit" value="&#10004; Submit">
                                            <input type="reset" class="btn btn-primary" name="cancel" value="&otimes; Cancel">
                                        </div> 
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

<!-- END -->
