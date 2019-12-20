<?php namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Lang;
use Validator;

use App\User;
use App\Models\Vendor;
use App\Models\Privilege;
use App\Models\AWS;
use App\Models\Image;
use App\Models\Setting;

class ImageController extends Controller {

    protected $user;

    public function image(Request $request) {
        
        /*$auth = User::auth($request, 'view_images');
        if (!$auth['status']) {
            $message = Lang::get($auth['error']);
                        return response()->json(compact('message'), 401);
        }
        $this->user = $auth['user'];
        */
        $input = $request->all();
        $validator = Validator::make($input, [
                        'order_id'  =>  'required|numeric|min:1',
                ]);

        if ($validator->fails()) {
            $message = $validator->messages()->first();
            return response()->json(compact('message'), 400);
        }

        $filters = array('order_id' => $input['order_id'], 'date' => date('Y-m-d'));                

                
                $data = Image::uploadImage($filters);
                return response()->json($data, 200);
    }
}
