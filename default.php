<?php
$PluginInfo['KarmaBankFlagging'] = array(
    'Name' => 'KarmaBank Flagging',
    'Description' => 'Extends KarmaBank to deduct Karma for flagging, as well as rewarding for being flags being dismissed.',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('KarmaBank' => '0.9.6.9b'),
    'Version' => '0.1b',
    'Author' => "Paul Thomas",
    'AuthorEmail' => 'dt01pqt_pt@yahoo.com'
);

class KarmaBankFlagging extends Gdn_Plugin {
    
    public function KarmaBank_KarmaBankMetaMap_Handler($Sender, $Args){
        if(!C('EnabledPlugins.Flagging'))
            return;
        $Sender->AddMeta(
            'FlaggedPosts', 
            'Counts when a post is flagged, but not multiple times for the same post. Suggestion: Use a negative amount.'
        );
        
        $Sender->AddMeta(
            'FlaggedPostsDismissed', 
            'Counts when a post flag is dismissed, but not multiple times for the same post. Suggestion: Use a positive amount.'
        );
    }
    
    public function Base_BeforeControllerMethod_Handler($Sender) {
        if(!Gdn::PluginManager()->GetPluginInstance('KarmaBank')->IsEnabled())
          return;
        if(!Gdn::Session()->isValid()) return;
        
        if(C('EnabledPlugins.Flagging')){
            if(strtolower($Sender->Controller())=='plugin'
                && strtolower($Sender->ControllerMethod())=='flagging'
                && strtolower(GetValue('0',$Sender->ControllerArguments())) == 'dismiss'){
                    if(!Gdn::Session()->CheckPermission('Garden.Moderation.Manage'))
                        return;
                    $Arguments = $Sender->ControllerArguments();
                    if (sizeof($Arguments) != 2) return;
                    list($Controller, $EncodedURL) = $Arguments;
                    $URL = base64_decode(str_replace('-','=',$EncodedURL));

                    $Parts = split('/',trim($URL,'/'));                     
                        
                    if(strtolower(GetValue('1',$Parts))=='comment'){
                        $Context = 'comment';
                        $ElementID = GetValue('2',$Parts);
                        $CommentModel = new CommentModel();
                        $Comment = $CommentModel->GetID($ElementID);
                        $ElementAuthorID = $Comment->InsertUserID;
                        
                    }else if(strtolower(GetValue('0',$Parts))=='discussion'){
                        $Context = 'discussion';
                        $ElementID = GetValue('1',$Parts);
                        $DiscussionModel = new DiscussionModel();
                        $Discussion = $DiscussionModel->GetID($ElementID);
                        $ElementAuthorID = $Discussion->InsertUserID;
                    }
                    
                    if($Context && $ElementAuthorID){
                        $PreFlagged = Gdn::SQL()
                           ->Select('fl.DateInserted')
                           ->From('Flag fl')
                           ->Where('fl.ForeignType', $Context)
                           ->Where('fl.ForeignID', $ElementID)
                           ->Where('fl.AuthorID', $ElementAuthorID)
                           ->Get()
                           ->FirstRow();
                        if($PreFlagged){
                            $FlaggedPosts = Gdn::UserModel()->GetMeta($ElementAuthorID, "FlaggedPostsDismissed");
                            Gdn::UserModel()->SetMeta($ElementAuthorID, array("FlaggedPostsDismissed"=> intval(GetValue('FlaggedPostsDismissed',$FlaggedPosts))+1));      
                        }
                    }
                    

                    
            }else if(strtolower($Sender->Controller())=='discussion'
                && strtolower($Sender->ControllerMethod())=='flag'){

                    $Arguments = $Sender->ControllerArguments();
                    if (sizeof($Arguments) != 5) return;
                    list($Context, $ElementID, $ElementAuthorID, $ElementAuthor, $EncodedURL) = $Arguments;
                    
                    if (Gdn::Request()->IsPostBack() && in_array(strtolower($Context), array('discussion', 'comment')) && ctype_digit($ElementID) && ctype_digit($ElementAuthorID)) {

                        $PreFlagged = Gdn::SQL()
                           ->Select('fl.DateInserted')
                           ->From('Flag fl')
                           ->Where('fl.ForeignType', $Context)
                           ->Where('fl.ForeignID', $ElementID)
                           ->Where('fl.AuthorID', $ElementAuthorID)
                           ->Get()
                           ->FirstRow();
                           
                        if(!$PreFlagged){
                            if(strtolower($Context)=='comment'){
                                $CommentModel = new CommentModel();
                                $Comment = $CommentModel->GetID($ElementID);
                                if(!$Comment || $ElementAuthorID != $Comment->InsertUserID)
                                    return;
                                
                            }else if(strtolower($Context)=='discussion'){
                                $Context = 'Discusion';
                                $DiscussionModel = new DiscussionModel();
                                $Discussion = $DiscussionModel->GetID($ElementID);
                                if(!$Discussion || $ElementAuthorID != $Discussion->InsertUserID)
                                    return;
                            }
                            $FlaggedPosts = Gdn::UserModel()->GetMeta($ElementAuthorID, "FlaggedPosts");
                            Gdn::UserModel()->SetMeta($ElementAuthorID, array("FlaggedPosts"=>intval(GetValue('FlaggedPosts',$FlaggedPosts))+1));
                        }
                        
                    }
            }
        }
        
    }
    
}
