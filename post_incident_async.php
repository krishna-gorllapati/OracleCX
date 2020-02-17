<?

/**
   Declare the name of this Object Event Handler:
 * CPMObjectEventHandler: post_incident_async

   Declare the package this Object Event Handler belongs to:
 * Package: RN

   Declare the objects this Object Event Handler can handle:
 * Objects: Incident

   Declare the actions this Object Event Handler can handle:
 * Actions: Update

   Declare the Connect Common Object Model version this
   Object Event Handler is bound to:
 * Version: 1.3
 */

// An alias to use for the version of the Custom Process Model
// this script is binding to:
use \RightNow\CPM\v1 as RNCPM;

// An alias use for the version of Connect for PHP that
// this script is binding to:
use \RightNow\Connect\v1_3 as RNCPHP;

\load_curl();

/**
 * This class contains the implementation of the Object Event Handler.
 * It must be the same name as declared above in the
 * CPMObjectEventHandler field in the header.
 */
class post_incident_async implements RNCPM\ObjectEventHandler
{

    
    /**
     * The apply() method "applies" the effects of this handler
     * for the given run_mode, action, object and cycle depth.
     * Upon a successful return (no errors, uncaught exceptions, etc),
     * a "commit" will be implicitly applied.
     * @param[in]      $run_mode may be one of:
     *                 RNCPM\RunMode{Live,TestObject,TestHarness}
     *
     * @param[in]      $action may be one of:
     *                 RNCPM\Action{Create,Update,Destroy}
     *
     * @param[in][out] $object is the Connect for PHP object that was
     *                 acted upon.  If $action is Update or Destroy,
     *                 then $object->prev *may* have the previous
     *                 values/state of the object before $action was
     *                 applied.
     *
     * @param[in]      $n_cycles is the number of cycles encountered
     *                 so far during this instance of $action upon
     *                 $object.
     */
    public static function apply( $run_mode, $action, $object, $n_cycles )
    {   
		echo( "--apply()-- \n" );  

		// Process tickets created by Salesforce only. Salesforce user id 13443188
		$createdBy = $object->CreatedByAccount->ID;
		if($createdBy != '13443188') {
			return;
		}
		
		
		// Find the latest note based on current datetime. Notes are called Threads in OCX.
		$currTime = 0;
		$currThread = null;
		$currThreadNotes = "";
		$currThreadCreateTime = "";
		$currThreadId = "NA";
		
		foreach ($object->Threads as $mythread) {
			if ($mythread->CreatedTime > $currTime) {
				$currTime = $mythread->CreatedTime;
				$currThread = $mythread;
			}
		}
		
		if ($currThread != null) {
			$currThreadNotes = $currThread->Text;
			$currThreadCreateTime = $currThread->CreatedTime;
			$currThreadId = $currThread->ID;
		}
		 
		$array_data = array(
			"Subject" => $object->Subject,
			"IncidentID" => $object-> ID,
			"ReferenceNumber " => $object->ReferenceNumber,
			"statusId" => $object->StatusWithType->Status->ID,
			"status" => $object->StatusWithType->Status->LookupName,
			"Notes" => $currThreadNotes,
			"CreatedTime" => $currThreadCreateTime,
			"NoteID" => $currThreadId
		);

		$json_data = json_encode($array_data);
		
		//echo "Json string = " . $json_data . "\n";
		if (!extension_loaded('curl'))
		{
		  load_curl();
		}
		
		//$eaiUrl = "https://staging.odplabs.com/services/case-upsert-service/eaiapi/OracleCxCreateCaseServices?token=123456789abcde";
		$eaiUrl = "https://api.officedepot.io/services/case-upsert-service/eaiapi/OracleCxCreateCaseServices?token=123456789abcde";
        
        $ch = curl_init($eaiUrl);
		curl_setopt($ch, CURLOPT_POST, count($json_data));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
		
		//curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		//curl_setopt($ch, CURLOPT_USERPWD, "SVC-PODTS:svcpodtsnp");
			
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1); // Enable when ready
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$temp = glob("/cgi-bin/*.db");
		curl_setopt($ch, CURLOPT_CAINFO, reset($temp) . "/certs/ca.pem");
		
		try
		{
		  $results = curl_exec($ch);
		}catch (Exception $e) {
		  echo 'Error message: ' .$e->getMessage();
		}
		
        if($results == false)
        {
            echo "Error: ".curl_error($ch);
        }
        curl_close($ch);
		
		$info = json_decode($results);
				
		//if ($info->status)
		//{
		//  if ($info->status != 200)
		//  {
		//	 print "<p>Error: ".$info->status; 
		//   }
		//}
        
        return;
    } //end apply
    
}

/**
 * This class contains the test harness for the Object Event Handler.
 * It must be the same name as declared above but with "_TestHarness"
 * added as a suffix on the name.
 */
class post_incident_async_TestHarness implements RNCPM\ObjectEventHandler_TestHarness
{    
    /**
     * setup() gives one a chance to do any kind of setup necessary
     * for the test.  The implementation can be empty, but it must exist.
     */
    public static function setup()
    {
        echo( "--setup()-- \n" );
        return;
    } //end setup


    /**
     * fetchObject() is invoked by the test harness to get the set
     * of objects to test with for the given action and object type.
     * @param[in]      $action may be one of:
     *                 RNCPM\Action{Create,Update,Destroy}
     *
     * @param[in]      $object_type is the PHP class name of the
     *                 Connect object type being tested.
     *
     * \returns the object or an array of objects to test with.
     */
    public static function fetchObject( $action, $object_type )
    {
        echo( "--fetchObject()--\n" );
        
        //[130919-003653 #8544901]
        /*
        //create a dummy contact for all the incidents
        $con = new RNCPHP\Contact();
        $con->Emails = new RNCPHP\EmailArray();
        $con->Emails[0] = new RNCPHP\Email();
        $con->Emails[0]->AddressType=new RNCPHP\NamedIDOptList();
        $con->Emails[0]->AddressType->LookupName = "Email - Primary";
        $con->Emails[0]->Address = "krishna.gorllapati@officedepot.com";
        $con->save();
        
        //create the original incident
        $inc_orig = new RNCPHP\Incident();
        $inc_orig->PrimaryContact = $con;
        $inc_orig->Subject = "Original Test Incident - Krishna";
        $inc_orig->save();
        
        
        //create the techmail incident (this one is passed into the EE)
        $inc = new RNCPHP\Incident();
        $inc->PrimaryContact = $con;
        $inc->Subject = "Test FNT Incident [" . $inc_orig->ReferenceNumber . " #" . $inc_orig->ID . "]";
        
        $t = new RNCPHP\Thread();
        $tmd = $t::getMetadata();
        $t->EntryType = new $tmd->EntryType->type_name;
        $t->EntryType->ID = 1;
        $t->MailHeader = "To: krishna.gorllapati@officedepot.com\nCC: cc_address@oracle.com.invalid\n";
        $t->Text = "Test thread from techmail incident.";
        
        $inc->Threads = new RNCPHP\ThreadArray();
        $inc->Threads[] = $t;
        
        $inc->save();
        
        return( $inc );
		*/
		return RNCPHP\Incident::fetch(51618680);
    } //end fetchObject


    /**
     * validate() is invoked by the test harness to validate the
     * expected effects of the given action upon the given object.
     * Throw an exception or return false to indicate failure.
     * @param[in]      $action may be one of:
     *                 RNCPM\Action{Create,Update,Destroy}
     *
     * @param[in]      $object is the Connect for PHP object that was
     *                 acted upon.
     *
     * \returns true if the test for $action, $object succeeded, or
     *          false otherwise.
     *          Throwing an exception is another way of communicating
     *          failure, and will display the exception text as a
     *          result of the test.
     */
    public static function validate( $action, $object )
    {
        echo( "--validate()--\n" );
		
		$createdBy = $object->CreatedByAccount->ID;
		echo "created by " . $createdBy	 . "\n\n";
		if($createdBy != '13443188') {
			echo 'Not processing...returning....';
			return;
		}
		
        $pass = TRUE;
		$inc_orig = $object;
        //$orig_i_id = "44385909";
         //$inc_orig = RNCPHP\Incident::fetch(receive_FNT_email::$orig_i_id);
		 //$inc_orig = RNCPHP\Incident::fetch(44385909);
        
         //echo "The original incident's status should be 8 'Updated', and the FNT incident's status should be 2 'Solved'\n";
         echo "Original incident: " . $inc_orig->StatusWithType->Status->ID . " '" . $inc_orig->StatusWithType->Status->LookupName . "'\n";
         echo "FNT incident: " . $inc_orig->StatusWithType->Status->ID . " '" . $inc_orig->StatusWithType->Status->LookupName . "'\n\n";
		 //echo "Created By: " . $inc_org->CreatedByAccount->LookupName . "\n";
		 //echo "Created By: " . $inc_org->CreatedByAccount . "\n";
        
        // echo "The newest thread in the original incident should roughly match this:\n";
        // echo "Forward & Track - from_address@oracle.com.invalid\n\n";
        // echo "Test thread from techmail incident.\n\n";
         //echo "Thread:\n" . $inc_orig->Threads[0]->Text . "\n\n";
        
        // echo "The original incident's to_and_cc custom field should contain to_address@oracle.com.invalid and cc_address@oracle.com.invalid\n";
        // echo "to_and_cc: " . $inc_orig->CustomFields->c->to_and_cc . "\n\n";
        
         //echo "The FNT incident's queue should be 486 'Forward And Track Response Archive'\n";
         //echo "to_and_cc: " . $inc_orig->Queue->ID . " '" . $inc_orig->Queue->LookupName . "'\n\n";
		 
		 
		 
		 //echo "subject " . $inc_orig->Subject . "'\n\n";
		// echo "notes " . $inc_orig->Notes . "'\n\n";
		// echo  "statustype " . $inc_orig->status_type . "'\n\n";
		// echo "status " . $inc_orig->status . "'\n\n";
		// echo "severity " . $inc_orig->severity_id . "'\n\n";
		echo "referencenumber " . $inc_orig->ReferenceNumber . "'\n\n";
		
		 //echo "queue " . $inc_orig->queue_id . "'\n\n";
		// echo "incidentid " . $inc_orig-> ID . "'\n\n";
		 //echo "fileattachment name " . $inc_orig->FileAttachments[0]->FileName . "'\n\n";
		 //echo "fileattachment date " . $inc_orig->FileAttachments[0]->CreatedTime . "'\n\n";
		 //echo "fileattachmentcount " . count($inc_orig->FileAttachments) . "'\n\n";
		 //echo "receiptname " . $inc_orig->CustomFields->c->RecipientName . "'\n\n";
		 //echo "customernumber " . $inc_orig->CustomFields->c->CustomerNumber . "'\n\n";
		 //echo "companyname " . $inc_orig->CustomFields->c->CompanyName . "'\n\n";
		 //echo "companyname " . $inc_orig->CustomFields->c->CompanyName . "'\n\n";
		 //echo "notes " . $inc_orig->CustomFields->c->ChatformQuestion . "'\n\n";
		 //echo "discussionthreads " . $inc_orig->Threads . "'\n\n";
		 //echo "discussionthreadcount " . count($inc_orig->Threads);
		 
		 /*foreach ($inc_orig->FileAttachments as $fileAttach) {
			echo "File Details...\n";
			echo $fileAttach->FileName . "\n";
			echo $fileAttach->CreatedTime . "\n";
			echo $fileAttach->UpdatedTime . "\n";
			echo $fileAttach->ContentType . "\n";
			echo $fileAttach->ID . "\n";
			echo "Attachment URL = " . "/services/rest/connect/v1.4/incidents/" . $inc_orig-> ID . "/fileAttachments/" . $fileAttach->ID . "\n";
		}
		*/
		
		$currTime = 0;
		$currThread = null;
		
		foreach ($inc_orig->Threads as $mythread) {
			// $arr[3] will be updated with each value from $arr...
			echo "Thread Details...\n";
			echo $mythread->Text . "\n";
			echo $mythread->CreatedTime . "\n";
			echo $mythread->DisplayOrder . "\n";
			//echo $mythread->ContentType . "\n";
			//echo $mythread->Contact->Name->First . "\n";
			//echo $mythread->Contact->Name->Last . "\n";
			echo $mythread->ID . "\n";
			if ($mythread->CreatedTime > $currTime) {
				$currTime = $mythread->CreatedTime;
				$currThread = $mythread;
			}
		}
		 
			echo "Current Thread Details...\n";
			echo $currThread->Text . "\n";
			echo $currThread->CreatedTime . "\n";
			echo $currThread->DisplayOrder . "\n";
			//echo $currThread->ContentType . "\n";
			//echo $currThread->Contact->Name->First . "\n";
			//echo $currThread->Contact->Name->Last . "\n";
			echo $currThread->ID . "\n";
		 
		 
		 
		
 

        //echo "Hello World";
        
        return( $pass );
    } //end validate


    /**
     * cleanup() gives one a chance to do any kind of post-test clean up
     * that may be necessary.
     * The implementation can be empty, but it must exist.
     * Note that the test harness is integrated with the Connect API such
     * that operations performed thru the Connect API are not committed,
     * so there's no need to clean up after the test even if it has created,
     * modified or destroyed objects via the Connect API.
     */
    public static function cleanup()
    {
        echo( "--cleanup()--\n" );
        return;
    } //end cleanup

}


