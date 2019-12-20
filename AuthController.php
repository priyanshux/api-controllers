<?php namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lang;
use JWTAuth;
use Validator;
use Mail;
use Queue;

use App\User;
use App\Models\Privilege;
use App\Models\Group;
use App\Models\SMSTemplate;
use App\Models\AuthLoginAttempt;
use App\Models\AuthSession;

class AuthController extends Controller {

    protected $groups;
    protected $user;
    
    public function __construct() {
        $this->groups = Group::get_list();
    }

	/**
	* @SWG\Post(
	*   path="/auth/signin",
	*   summary="User Authentication",
	*   tags={"Auth"},
	*   description="Authenticates user using email and password and returns a auth token",
	*   operationId="authSignin",
	*   consumes={"application/json"},
	*   produces={"application/json"},
	*	@SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*	name="body",
	*       in="body",
	*       description="User login credential ",
	*       required=true,
	*       @SWG\Schema(ref="#/definitions/AuthSigninRequest"),
	*	),
	*   @SWG\Response(
	*       response=200,
	*       description="Auth token, User details, Privileges",
        *      @SWG\Schema(ref="#/definitions/AuthSigninResponse"),
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel"),
	*   ),
	*   @SWG\Response(
	*       response="401",
	*       description="Incorrect Auth",
        *       @SWG\Schema(ref="#/definitions/MessageModel"),
	*   )	
	* )
        *   @SWG\Definition(
        * 	definition="AuthSigninRequest",
        * 	required={"email", "password"},
        * 	@SWG\Property(property="email", type="string", example="rajnesh.rajput@vvdntech.in"),
        *       @SWG\Property(property="password", type="string", example="1234")
        *   ),
        * @SWG\Definition(
        * 	definition="AuthSigninResponse",
        * 	required={"token", "message", "privileges", "user"},
        * 	@SWG\Property(property="token", type="string", description="Auto generate string"),
        * 	@SWG\Property(property="message", type="string", example="You have successfully signed in."),
        * 	@SWG\Property(property="privileges", type="array", @SWG\Items(type="string", example="insert_user")),
        * 	@SWG\Property(
        *           property="user", 
        *           @SWG\Property(property="id", type="integer", example=16377), 
        *           @SWG\Property(property="name", type="string", example="Rajnesh"), 
        *           @SWG\Property(property="email", type="string", example="rajnesh.rajput@vvdntech.in"), 
        *           @SWG\Property(property="group_id", type="integer", example=2),
        *           @SWG\Property(property="group", type="string", example="admin")
        *       ),
        *   )
	*/
	
    public function signin(Request $request) {

        $input = $request->all();
        $validator = Validator::make($input, [
            'email' => 'required',
            'password' => 'required'
        ]);
        if ($validator->fails()) {
            $message = $validator->messages()->first();
            return response()->json(compact('message'), 400);
        }

        $credentials = $request->only('email', 'password');
        $credentials = array_map('trim', $credentials);
        $credentials['email'] = strtolower($credentials['email']);
        $credentials['banned'] = 0;
		
		$user = User::where('email', $credentials['email'])->where('banned', $credentials['banned'])->first();				
        if (is_null($user)) {
            $message = Lang::get('messages.incorrect_login');
            return response()->json(compact('message'), 401);
        }
		
		$password = User::hashPassword($user->email, $credentials['password']);	
		if ($user->password != $password) {
			$message = Lang::get('messages.incorrect_login');
            return response()->json(compact('message'), 401);
		}

        $token = JWTAuth::fromUser($user);
			
		$data = array(
			'token' => $token,
			'message' => Lang::get('messages.auth_successful'),
		);
		$data['privileges'] = Privilege::getForUser($user->id, $user->group_id);
		$data['user'] = array('id' => $user->id, 'name' => $user->firstname, 'email' => $user->email, 'group_id' => $user->group_id, 'group' => $this->groups[$user->group_id]);
					
		return response()->json($data);
		
    }
    
    /**
	* @SWG\Post(
	*   path="/auth/generate/otp",
	*   summary="User generate otp",
	*   tags={"Auth"},
	*   description="Generate mobile otp",
	*   operationId="authGenerateOtp",
	*   consumes={"application/json"},
	*   produces={"application/json"},
	*	@SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*	name="body",
	*       in="body",
	*       description="User login credential ",
	*       required=true,
	*       @SWG\Schema(ref="#/definitions/AuthGenerateOtpRequest"),
	*	),
	*   @SWG\Response(
	*       response=200,
	*       description="Success Message",
        *      @SWG\Schema(ref="#/definitions/MessageModel"),
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel"),
	*   ),
	*   @SWG\Response(
	*       response="401",
	*       description="Incorrect Auth",
        *       @SWG\Schema(ref="#/definitions/MessageModel"),
	*   )	
	* )
        *   @SWG\Definition(
        * 	definition="AuthGenerateOtpRequest",
        * 	required={"mobile"},
        * 	@SWG\Property(property="mobile", type="integer", example=9999234678),
        *       @SWG\Property(property="retry", type="boolean", example=true)
        *   )
	*/
	
    public function generate_otp(Request $request) {

            $input = $request->all();
            $validator = Validator::make($input, [
                        'mobile' => 'required|numeric',
                        'retry' => 'bool',
            ]);
            if ($validator->fails()) {
                $message = $validator->messages()->first();
                return response()->json(compact('message'), 400);
            }

            $credentials = $request->only('mobile');
            $credentials = array_map('trim', $credentials);
            $credentials['banned'] = 0;
		
            $user = User::where('phone', $credentials['mobile'])->where('banned', $credentials['banned'])->where('group_id', '!=',4)->first();				
            if (is_null($user)) {
                $message = Lang::get('messages.incorrect_mobile');
                return response()->json(compact('message'), 401);
            }
            
            $retry = FALSE;
            if(isset($input['retry']) && $input['retry']) {
                    $retry = TRUE;
            }
            
            if ($retry && is_null($user->otp)) {
                $message = Lang::get('messages.retry_not_valid');
                return response()->json(compact('message'), 401);
            }
            
            $expire_time = strtotime('now') + (5*60);
            
            $msgtxt = '';
            $otp = '';
            $type = 'text';
            
            if($retry === FALSE){
                    $otp = mt_rand(1000, 9999);
                    if (env('APP_ENV') != 'production') {
                            $otp = 1234;
                    }
                    $user->otp = $otp;
                    $message_template = SMSTemplate::where('name','login_otp')->where('flag', 1)->first();
                    $msgtxt = vsprintf($message_template['text'], array(date('h:i a', $expire_time)));
            }
            
            $user->otp_expire_on = date('Y-m-d H:i:s', $expire_time);
            $user->save();
            
            $sms_data = array(			'mobile'    =>  $user->phone,
                                                'message'   =>  $msgtxt,
                                                'otp'       =>  $otp,
                                                'retry'     =>  $retry,
                                                'type'      =>  $type,
                                                'sms_type'  =>	'sms_otp'
                                    );

            $sqs_url = config('queue.connections.sqs.prefix').config('queue.connections.sqs.sms_queue');
            Queue::pushRaw(json_encode($sms_data), $sqs_url);
            
            $data['message'] = Lang::get('messages.message_delivered');
            return response()->json($data, 200);
		
    }
    
    /**
	* @SWG\Post(
	*   path="/auth/login/otp",
	*   summary="User login otp",
	*   tags={"Auth"},
	*   description="Authenticates user using mobile and otp and returns a auth token",
	*   operationId="authLoginOtp",
	*   consumes={"application/json"},
	*   produces={"application/json"},
	*	@SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*	name="body",
	*       in="body",
	*       description="User login credential ",
	*       required=true,
	*       @SWG\Schema(ref="#/definitions/AuthLoginOtpRequest"),
	*	),
	*   @SWG\Response(
	*       response=200,
	*       description="Auth token, User details, Privileges",
        *      @SWG\Schema(ref="#/definitions/AuthSigninResponse"),
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel"),
	*   ),
	*   @SWG\Response(
	*       response="401",
	*       description="Incorrect Auth",
        *       @SWG\Schema(ref="#/definitions/MessageModel"),
	*   )	
	* )
        *   @SWG\Definition(
        * 	definition="AuthLoginOtpRequest",
        * 	required={"mobile", "otp"},
        * 	@SWG\Property(property="mobile", type="integer", example=9999234678),
        *       @SWG\Property(property="otp", type="integer", example=1234)
        *   )
	*/
    
    public function login_by_otp(Request $request) {

            $input = $request->all();
            $validator = Validator::make($input, [
                        'mobile' => 'required|numeric',
                        'otp' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                $message = $validator->messages()->first();
                return response()->json(compact('message'), 400);
            }
            $ip = $request->ip();
            $data = $request->only('mobile', 'otp');
            
            if (AuthLoginAttempt::is_max_login_attempts_exceeded($ip, $data['mobile'])) {
                    $message = Lang::get('messages.login_max_attempt');
                    return response()->json(compact('message'), 400);
            }
			
            $user = User::where('phone', $data['mobile'])->where('otp', $data['otp'])->where('otp_expire_on', '>=', date('Y-m-d H:i:s'))->where('group_id', '!=',4)->first();			
            
            if (is_null($user)) {
                    AuthLoginAttempt::increase_login_attempt($ip, $data['mobile']);
                    $message = Lang::get('messages.otp_incorrect');
                    return response()->json(compact('message'), 400);
            }
            
            AuthLoginAttempt::clear_login_attempts($ip, $data['mobile']);
            
            $token = User::hashPassword($user->phone, $user->password);
            AuthSession::clear_auth_sessions($user->id);
            AuthSession::create_auth_session($user->id, $token);
	    
            $user->otp = NULL;
            $user->otp_expire_on = NULL;
            $user->save();
            
            $data = array(
                    'token' => $token,
                    'message' => Lang::get('messages.auth_successful'),
            );
            $data['privileges'] = Privilege::getForUser($user->id, $user->group_id);
            $data['user'] = array('id' => $user->id, 'name' => $user->firstname, 'email' => $user->email, 'group_id' => $user->group_id, 'group' => $this->groups[$user->group_id]);

            return response()->json($data);
		
    }

	/**
	* @SWG\Post(
	*   path="/auth/password",
	*   summary="Initiate Password Recovery",
	*   tags={"Auth"},
	*   description="Initiate psasword recovery by sending a one time password",
	*   operationId="authForgotPassword",
	*   consumes={"application/json"},
	*   produces={"application/json"},
	*	@SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*	name="body",
	*       in="body",
	*       description="User Email",
	*       required=true,
	*       @SWG\Schema(ref="#/definitions/AuthPasswordRequest"),
	*	),
	*   @SWG\Response(
	*       response=202,
	*       description="OTP sent to email for resetting password",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   )	
	* )
        * @SWG\Definition(
        * 	definition="AuthPasswordRequest",
        * 	required={"email"},
        * 	@SWG\Property(property="email", type="string", example="rajnesh.rajput@vvdntech.in")
        *   )
	*/
	
    public function forgotPassword(Request $request) {

        $input = $request->all();
        $input = array_map('trim', $input);
        $validator = Validator::make($input, [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            $message = $validator->messages()->first();
            return response()->json(compact('message'), 400);
        } else {
            # check if user exists
            $user = User::where('email', $input['email'])->where('banned', 0)->first();
            if (!is_null($user)) {
                $user->otp = $otp = mt_rand(1001, 9998);
                $user->save();

                Mail::send('emails.forgot_password', ['name' => $user->firstname, 'otp' => $otp], function ($m) use ($user) {
                    $m->from(config('milkbasket.webmaster_email'), config('milkbasket.webmaster_name'));
                    $m->to($user->email);
                    $m->subject(Lang::get('emails.forgot_password', []));
                });

                $message = Lang::get('messages.password_otp_sent');
                return response()->json(compact('message'), 202);
            } else {
                $message = Lang::get('messages.account_not_found');
                return response()->json(compact('message'), 400);
            }
        }
    }

	/**
	* @SWG\Post(
	*   path="/auth/password/reset",
	*   summary="Reset Password",
	*   tags={"Auth"},
	*   description="Reset psasword using the OTP received on email",
	*   operationId="authResetPassword",
	*   consumes={"application/json"},
	*   produces={"application/json"},
	*	@SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*		default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*    @SWG\Parameter(
	*	name="body",
	*       in="body",
	*       description="Reset Password data",
	*       required=true,
	*       @SWG\Schema(ref="#/definitions/AuthPasswordResetRequest"),
	*	),	
	*   @SWG\Response(
	*       response=200,
	*       description="Password reset successully",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   )	
	* )
        * @SWG\Definition(
        * 	definition="AuthPasswordResetRequest",
        * 	required={"email"},
        * 	@SWG\Property(property="email", type="string", example="rajnesh.rajput@vvdntech.in"),
        * 	@SWG\Property(property="password", type="string", example="123456"),
        * 	@SWG\Property(property="otp", type="string", example="1234")
        *   )
	*/
	
    public function resetPassword(Request $request) {

        $input = $request->all();
        $input = array_map('trim', $input);
        $validator = Validator::make($input, [
            'email' => 'required|email',
            'otp' => 'required|size:4',
            'password' => 'required|min:3',
        ]); 

        if ($validator->fails()) {
            $message = $validator->messages()->first();
            return response()->json(compact('message'), 400);
        } else {
            # check if user exists
            $user = User::where('email', $input['email'])->where('banned', 0)->first();
            if (!is_null($user)) {
                if ($user->otp == $input['otp']) {
					
					$user->otp = $user->activated ? null : $input['password'];
                    $user->password = User::hashPassword($user->phone, $input['password']);
                    $user->save();
					
				/*	$vars = array(
						'user_id'	=>	$user->id,
						'password'	=>	$input['password'],
					);
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, env('MAIN_APP_SERVER').'/admin/sync_backend/update_password');
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($vars));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_exec($ch);
					curl_close($ch);
				*/
                    $data = array(
                        'message' => Lang::get('messages.password_reset_successful'),
                    );
                    return response()->json($data);
                } else {
                    $message = Lang::get('messages.incorrect_otp');
                    return response()->json(compact('message'), 400);
                }
            } else {
                $message = Lang::get('messages.account_not_found');
                return response()->json(compact('message'), 400);
            }
        }
    }
	
    public function invalidate(Request $request) {

        $input = $request->all();
        $input = array_map('trim', $input);
        if (!$user = JWTAuth::parseToken()->authenticate()) {
            $message = Lang::get('messages.user_not_found');
            return response()->json(compact('message'), 404);
        } else {
            $user->banned = time();
            $user->banned_reason = $input['reason'];
            $user->save();
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(true);
        }
    }


   
}
