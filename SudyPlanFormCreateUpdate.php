<?
/*
 * CPMObjectEventHandler: StudyPlanRequest
 * Package: CO
 * Objects: CO\StudyPlanRequest
 * Actions: Create, Update
 * Version: 1.2
 */
 
 //Purpose of the script: Automatically create a new incident of type 'study plan' and bind it with newly created StudyPlanRequest record.
 
// This object procedure binds to v1_1 of the Connect PHP API

use \RightNow\Connect\v1_2 as RNCPHP;
// This object procedure binds to the v1 interface of the process
// designer
use \RightNow\CPM\v1 as RNCPM;
/**
 * An Object Event Handler must provide two classes:
 *   - One with the same name as the CPMObjectEventHandler tag
 *     above that implements the ObjectEventHandler interface.
 *   - And one of the same name with a "_TestHarness" suffix
 *     that implements the ObjectEventHandler_TestHarness interface.
 *
 * Each method must have an implementation.
 */
class StudyPlanRequest
        implements RNCPM\ObjectEventHandler
{
    public static function apply( $run_mode, $action, $obj, $n_cycles )
    { 
				$primaryContact=$obj->contact_id;
					
				$incidentText=	self::createIncidentText($obj);
				$queueName= self::assignQueue($obj);
				$status=self::assignStatus($obj);
				
				
				if($action == 2)
				{
					
					$subject= self::getSubject($primaryContact,true);
					$incident=$obj->incident_id;
					$incident=self::createUpdateIncident($status,$queueName,$subject,$incidentText,$primaryContact,$incident);	
						
				}
				else
				{
					$subject= self::getSubject($primaryContact,false);
					$incident=NULL;
					$incident=self::createUpdateIncident($status,$queueName,$subject,$incidentText,$primaryContact,NULL);	
					
					$obj->incident_id=$incident;
					$obj->save(RNCPHP\CO\StudyPlanRequest::SuppressAll);
						
				
				}
				
				//include reference number in the subject line,
				$subject .= " [Enquiry:" . $incident->ReferenceNumber . "]";
				
				if ($obj->under_review==1)
					{
						$standardTextName='Progression Email address for SPR';
				
				
						$standardContent =RNCPHP\StandardContent::find("Name = '".$standardTextName."'");  //RNCPHP\StandardContent::fetch(79);
						var_dump($standardContent[0]->ContentValues[0]->Value);
						//text value of standard content
						$progressionEmailAddress=$standardContent[0]->ContentValues[0]->Value ;	
						
					
						//prepare instructional email message
						$message= self::prepareMessage($incident);
						
						//send email to progression
						self::sendEmail($subject,$message,$progressionEmailAddress);
						$note="Request emailed to Progression team- " . $progressionEmailAddress;
									
						self::addPrivateNote($incident,$note);
					}	
						
			if($obj->notify_student==1)
				{	
					$messageHeader="Thank you for contacting the Academic Liaison Unit (ALU), Charles Darwin University.<br/><br/>
					Please find below a copy of your study plan request and respond via return email if you would like to make any amendments. <br/><br/>";
					
					$messageFooter=" <br/> Our aim is to provide a Recommended Study Plan for your consideration within five (5) working days. <br/>
A copy of this email has been sent to your official CDU email address and all other addresses that are linked to your student email account.
";
					
										
					$message = $messageHeader .  $incidentText .$messageFooter ;
					
					$emails=$primaryContact->Emails;
					
					if(count($emails)>0)
					{
						//send email to student's Primary email and primary emails
						var_dump($emails[0]->Address);
						self::sendEmail($subject,$message,$emails[0]->Address);
						var_dump(count($emails));
						if (count($emails)>1)
						{	var_dump($emails[1]->Address);
							self::sendEmail($subject,$message,$emails[1]->Address);
						}
						
						self::addAgentEntryToIncident($incident,$message);
					}
				}
				
				


		return ;
	}
	private static function addAgentEntryToIncident(&$incident,$message)
	{
	
				$thread = new RNCPHP\Thread;
				$thread->EntryType = new RNCPHP\NamedIDOptList();
				$thread->ContentType=new RNCPHP\NamedIDOptList();
				$thread->ContentType->ID=2;
				$thread->EntryType->ID = 2; //agent thread
				$thread->Text = $message;
				$incident->Threads[count($incident->Threads)] = $thread;
			
				$incident->save(RNCPHP\RNObject::SuppressAll);
			
				return;
			
			
			
	}
	private static function assignStatus($obj)
	{
		if ($obj->under_review==1)
			{
				$status="Solved";
			}
			
		else{
				$status="Unresolved";
			}
		return $status;	
	}
	private static function assignQueue($obj)
	{
				if ($obj->international==1)
					{$queueName="ALU Intl Study Plans";}
				else
					{$queueName="ALU Study Plan";}	
				return $queueName;
	}
	private static function getSubject($contact,$updated)
	{
				
				//construct subject for email and incident
				if ($updated)
				{
					$subject="UPDATED- ";
				}
				$subject .= "Study Plan Request - ";
				$subject .= $contact->CustomFields->c->student_number . "  ";
				$subject .= $contact->Name->First . " ";
				$subject .= $contact->Name->Last;	
				
				return $subject;	
				
	}
	
	private static function addPrivateNote(&$incident,$p_note)
	{
				$thread = new RNCPHP\Thread;
				//$md = RNCPHP\Thread::getMetadata();
				$thread->EntryType = new RNCPHP\NamedIDOptList(); //new $md->EntryType->type_name;
				$thread->EntryType->ID = 1; //private note
				$thread->Text = $p_note;
				$incident->Threads[count($incident->Threads)] = $thread;
					
				
				$incident->save(RNCPHP\RNObject::SuppressAll);
				return;
	}

	private static function createUpdateIncident($status,$queueName,$subject,$incidentText,$contact,$incident)
	{
	
	
			if ($incident==NULL) // if creating a new incident
			{	var_dump($incident);
				$incident = new RNCPHP\Incident();
				$incident->PrimaryContact= $contact;
				
				$incident->CustomFields->c->type=new RNCPHP\NamedIDLabel();
				$incident->CustomFields->c->type->LookupName="Study Plan Request" ;
				
				$incident->Threads = new RNCPHP\ThreadArray();
				$incident->Threads[0] = new RNCPHP\Thread();
				$incident->Threads[0]->EntryType = new RNCPHP\NamedIDOptList();
				$incident->Threads[0]->ContentType=new RNCPHP\NamedIDOptList();
				$incident->Threads[0]->ContentType->ID=2; //HTML content
				$incident->Threads[0]->EntryType->ID = 3; // Used the ID here. See the Thread object for definition
			}
			
				$incident->Subject=$subject;
				
				$incident->StatusWithType = new RNCPHP\StatusWithType();
				$incident->StatusWithType->Status->LookupName = $status;
				
				$incident->Queue= new  RNCPHP\NamedIDLabel();
				$incident->Queue->LookupName=$queueName;
				$incident->Threads[count($incident->Threads)-1]->Text = $incidentText;//add new Thread on the top
						
				
				$incident->save(RNCPHP\RNObject::SuppressAll);
				return $incident;
	}
	
	private static function sendEmail($subject,$message,$emailAddress )
	{		
					$override_standard_process = true;
					$mailArray = array('to' => $emailAddress , 'reply-to' => 'alu@cdu.edu.au', 'from' => 'alu@cdu.edu.au',
					'subject' => $subject , 'html' => $message);
				
					include("custom/fnt/forward_submit.php");
	}
	
	
	private static function prepareMessage($incident)
	{
						$message="Dear Progression Team,<br/>Can you please action below study plan request for above student currently Under Progression? <br/><br/> Thanks! <br/><br/>";
		
						//append incident text to email message
						$message .= $incident->Threads[0]->Text;
										
		return $message;
		
	}
	
	
	private static function createIncidentText($studyPlanRequest)
	{
		
		$studyPlan=  $studyPlanRequest; 
		
		$outstandingApplications =(($studyPlan->outstanding_applications == 1) ? 'YES' : 'NO');
		$international =(($studyPlan->international == 1) ? 'YES' : 'NO') ;
		$previousStudyPlan =(($studyPlan->previous_study_plan == 1) ? 'YES' : 'NO') ;
		$underReview=(($studyPlan->under_review == 1) ? 'YES' : 'NO') ;
		$reduceLoad =(($studyPlan->study_load == 1) ? 'YES' : 'NO') ;
		
		$courseCode = $studyPlan->course_code ;
		$specialisation =$studyPlan->specialisation  ;

		

		$otherComments =$studyPlan->other_comments ;
		
		$units_s1=$studyPlan->units_s1;
		$units_s2=$studyPlan->units_s2;
		$units_ss=$studyPlan->units_ss;
		
		
		$message= "<table cellspacing=\"2\">
						
						<tr>
								<td>Course Code:</td><td>" . $courseCode . "</td>
						</tr>
						
						<tr>
								<td>Specialisation:</td><td>". $specialisation. "</td>
						</tr>
						
						<tr>
								<td>International:</td><td>". $international . "</td>
						</tr>
											
						<tr>
								<td>Reduce Load:</td><td>". $reduceLoad . "</td>
						</tr>
						
						<tr>
								<td>Outstanding Applications:</td><td>". $outstandingApplications . "</td>
						</tr>
						
						<tr>
								<td>Under Progression:</td><td>". $underReview ."</td>
						</tr>
						
												
						<tr bgcolor=\"#CCCCCC\">
								<td  colspan=\"2\"><b>Units Each Semester:</b></td>
						</tr>
						
						<tr bgcolor=\"#CCCCCC\">
							
								<td >Semester 1:</td><td>" . $units_s1 . "</td>
						
						</tr>
						
						<tr bgcolor=\"#CCCCCC\">
								<td >Semester 2:</td><td>" . $units_s2 . "</td>
						</tr>
						
						<tr bgcolor=\"#CCCCCC\">
								<td >Summer Semester:</td><td>" . $units_ss . "</td>
						</tr>
																			
								
						<tr>
								<td>Other Comments:</td><td>". $otherComments . "</td>
						</tr>
						
				 </table>";

		return $message;
		
	}
}
 class StudyPlanRequest_TestHarness
        implements RNCPM\ObjectEventHandler_TestHarness
{
    static $org_invented = NULL;
    public static function setup()
    {
  		$studyplanform_id="214";
		$studyplanform = RNCPHP\CO\StudyPlanRequest::fetch($studyplanform_id);
       static::$org_invented = $studyplanform;
        return;
    }
    public static function
    fetchObject( $action, $object_type ){
        // Return the object that we
        // want to test with.
        // You could also return an array of objects
        // to test more than one variation of an object.
        return(static::$org_invented);
    }
    public static function
    validate( $action, $object )
    {
        // Add one note.
        return(true);
    }
    public static function cleanup()
    {
        // Destroy every object invented
        // by this test.
        // Not necessary since in test
        // mode and nothing is committed,
        // but good practice if only to
        // document the side effects of
        // this test.
        //static::$org_invented->destroy().
        static::$org_invented = NULL;
        return;
    }
}