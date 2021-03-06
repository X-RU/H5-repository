<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Activity;
use App\UserActivity;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class UserController extends Controller{

	/**
	 * 验证用户是否登录
	 */
	public function checkLogin() {
		if (Auth::check()){
			return response()->json(['code' => '200', 'message' => 'success']);
		}
		return response()->json(['code' => '400', 'message' => 'failure']);
	}

	/**
	 * 获取用户信息
	 * @param  Request $request
	 * @return view:'user_msg';data:[...]
	 *
	 */
	public function getUser() {
		$user = Auth::user();
		return response()->json(['code' =>'200', 'message' => 'success', 'data' =>$user]);
	}

	/**
	 * 使用微博登录：获取code
	 *
	 * @return redirect to https://api.weibo.com/oauth2/authorize
	 */
	public function weiboLogin() {

		$client_id = '1528221042';
		$redirect_uri = urlencode('http://h5.jayna.fun/user/access_token');
		$display = 'mobile';
		return redirect("https://api.weibo.com/oauth2/authorize?client_id=$client_id&redirect_uri=$redirect_uri&display=$display");
	}

	/**
	 * 使用微博登录:获取access_token
	 *
	 * @return redirect to https://api.weibo.com/oauth2/access_token
	 */
	public function accessToken(Request $request) {
		$code = $request['code'];
		$params = [
			'client_id' => '1528221042',
			'client_secret' => '7dc8b9448e42a8a1be7c3021f7fb1ba6',
			'grant_type' => 'authorization_code',
			'redirect_uri' => 'http://h5.jayna.fun/user/access_token',
			'code' => $code
		];
		$http = new Client();
		$requestBody = $http->request('POST', "https://api.weibo.com/oauth2/access_token",['form_params' => $params])->getBody();

		$contents = json_decode($requestBody->getContents(), true);
		$access_token = $contents['access_token'];
		$uid = $contents['uid'];
		return redirect()->action('UserController@getWeiboUser', ['access_token'=>$access_token, 'uid'=>$uid]);
	}


	public function getWeiboUser(Request $request){
		//从request中获取数据
		$access_token = $request['access_token'];
		$uid = $request['uid'];
		//使用Client发起http请求
		$http = new Client();
		//从微博获取用户信息
		$response = $http->get("https://api.weibo.com/2/users/show.json?access_token=$access_token&uid=$uid");
		//用json_decode(string, boolean)方法将string转化为json
		$data = json_decode((string)$response->getBody(), true);

		$user = new User;

		$id = $data['id'];
		$user_db = User::find($id);

		if(null == $user_db){
			$user->id = $id;
			$user->idStr = $data['idstr'];
			$user->screen_name = $data['screen_name'];
			$user->province = $data['province'];
			$user->city = $data['city'];
			$user->location = $data['location'];
			$user->description = $data['description'];
			$user->profile_Image_url = $data['profile_image_url'];
			$user->profile_url = $data['profile_url'];
			$user->gender = $data['gender'];
			$user->remark = $data['remark'];
			$user->avatar_hd = $data['avatar_hd'];
			$user->online_status = $data['online_status'];
			$user->lang = $data['lang'];
			$user->api_token = str_random(60);
			$user->remember_token = "";
			// 将微博数据保存到用户表中
			$user->save();
			// 利用用户进行权限验证
			Auth::login($user,true);
			return Redirect::to('/');
		}
		else{
			// 利用用户进行权限验证
			Auth::login($user_db,true);
			return Redirect::to('/');
		}
	}


	public function activityList(){

		$user = User::Auth();
		$user_id = $user->id;

		$theActivitiesICreate = array();

		//查询用户创建的活动
		$theActivitiesICreate = Activity::where('init_user_id',$user_id)->get();

		//查询用户参加的活动关系
		$activity_relations = UserActivity::where('user_id',$user_id)->get();

		//创建活动id列表
		$activity_id_list = array();

		//根据用户活动关系获取活动id
		foreach($activity_relations as $activity_relation){

			array_push($activity_id_list,$activity_relation->activity_id);

		}

		$theActivitiesIAttend = array();

		//我参加的活动
		$theActivitiesIAttend = Activity::find($activity_id_list);

		$total = $theActivitiesIAttend->merge($theActivitiesICreate);

		$userController = new UserController;

		return $userController->response_cjj($total,'200','success');
	}

	public function updateUser(Request $request){
		//获取当前登录的用户
		$user = Auth::user();
		$profile_Image_url = $request->input('profile_Image_url');
		$user->profile_Image_url = $profile_Image_url;
		$user->save();
		$userController = new UserController;
		return $userController->response_cjj($user,'200','success');
	}

	//装饰response接口
	public function response_cjj($data, $code = '200', $message = 'success'){

		return response()->json(['code' =>$code, 'message' => $message, 'data' =>$data]);
	}
}
