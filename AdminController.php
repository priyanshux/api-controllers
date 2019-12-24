<?php namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lang;
use JWTAuth;
use Validator;
use Mail;

use App\User;
use App\Models\Vendor;

class AdminController extends Controller {

    protected $user;

    public function __construct() {

    }
	
	public function store(Request $request) {
		
		$input = $request->all();
        $validator = Validator::make($input, [
			'id'		=>	'required|exists:users,id',
            'group' 	=>	'required|exists:groups,id',
			'name'		=>	'required',
			'email'		=>	'required',
			'password'	=>	'required',			
        ]);
		
        if ($validator->fails()) {
            $message = $validator->messages()->first();
            return response()->json(compact('message'), 400);
        }
		
        $user = User::where('email', $input['email'])->first();
		if (is_null($user)) {
			
			$user = new User();
			$user->id = $input['id'];
			$user->group_id = $input['group'];
			$user->firstname = $input['name'];
			$user->email = strtolower($input['email']);
			$user->password = User::hashPassword($input['email'], $input['password']);
			$user->save();
			
			$data = array(
				'message' => Lang::get('messages.user_created'),
			);
			return response()->json($data);
		} else {
			$message = Lang::get('messages.account_exists');
			return response()->json(compact('message'), 400);
		}
	}
	
	public function update($user_id, Request $request) {
		
		$input = $request->all();
        $validator = Validator::make($input, [
			'name'		=>	'required',
			'email'		=>	'required',
        ]);
		
        if ($validator->fails()) {
            $message = $validator->messages()->first();
            return response()->json(compact('message'), 400);
        }
		
        $user = User::where('id', $user_id)->first();
		if (!is_null($user)) {
			
			$user->firstname = $input['name'];
			if (isset($input['password']) && $input['password']) {
				$user->password = User::hashPassword($input['email'], $input['password']);
			}
			$user->save();
			
			$input['email'] = strtolower($input['email']);
			if ($user->email != $input['email']) {
				$email_user = User::where('email', $input['email'])->first();
				if (is_null($email_user)) {
					$user->email = $input['email'];
					$user->save();
				} else {
					$message = Lang::get('messages.email_exists_for_another_user');
					return response()->json(compact('message'), 400);
				}
			}
			
			$data = array(
				'message' => Lang::get('messages.user_updated'),
			);
			return response()->json($data);
		} else {
			$message = Lang::get('messages.account_not_found');
			return response()->json(compact('message'), 400);
		}
	}	
	
	public function destroy($user_id, Request $request) {
		
		$user = User::where('id', $user_id)->first();
		if (!is_null($user)) {
			
			$user->banned = 1;
			$user->save();
			
			$data = array(
				'message' => Lang::get('messages.user_deleted'),
			);
			return response()->json($data);
		} else {
			$message = Lang::get('messages.account_not_found');
			return response()->json(compact('message'), 400);
			
		}
	}		
}