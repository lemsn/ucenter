<?php namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Model\User;
use App\Model\Role;
use App\Model\UserRole;
use App\Model\App;
use Redirect, Input, Auth;
use Illuminate\Pagination\Paginator;
use App\Services\Helper;

use Monolog\Logger;
use Monolog\Handler\RedisHandler;

use Cache;
use Queue;
use Session;
use App\Jobs\UserLog;
use App\Jobs\SendEmail;
use Mail;
use Config;
use App\Services\Api;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.user.index');
    }

    public function lists(Request $request)
    {
        // 当前应用用户的id
        $userIdsArray = UserRole::where('app_id', Session::get('current_app_id'))->lists('user_id');

        $fields = array('id', 'username', 'email', 'phone', 'created_at', 'updated_at');
        $searchFields = array('username', 'email', 'phone');

        $data = User::whereIn('id', $userIdsArray)
            ->with(['appRoles' => function($query) {
                $query->where('roles.app_id', Session::get('current_app_id'));
            }])
            ->whereDataTables($request, $searchFields)
            ->orderByDataTables($request)
            ->skip($request->start)
            ->take($request->length)
            ->get($fields);
        $draw = (int)$request->draw;
        $recordsTotal = User::whereIn('id', $userIdsArray)->count();
        $recordsFiltered = strlen($request->search['value']) ? count($data) : $recordsTotal;

        return Api::dataTablesReturn(compact('draw', 'recordsFiltered', 'recordsTotal', 'data'));
    }

    // 用户总库
    public function all(Request $request)
    {
        return view('admin.user.all');
    }

    public function allLists(Request $request)
    {
        $fields = array('id', 'username', 'email', 'phone', 'created_at', 'updated_at');
        $searchFields = array('username', 'email', 'phone');

        $data = User::whereDataTables($request, $searchFields)
            ->orderByDataTables($request)
            ->skip($request->start)
            ->take($request->length)
            ->get($fields);

        $roles = UserRole::where('app_id', Session::get('current_app_id'))->lists('user_id')->toArray();
        foreach ($data as $k => $v) {
            if (in_array($v['id'], $roles)) {
                $v['status'] = 1;
            } else {
                $v['status'] = 0;
            }
        }


        $draw = (int)$request->draw;
        $recordsTotal = User::count();
        $recordsFiltered = strlen($request->search['value']) ? count($data) : $recordsTotal;

        return Api::dataTablesReturn(compact('draw', 'recordsFiltered', 'recordsTotal', 'data'));
    }

    public function create()
    {
        return view('admin.user.create');
    }

    // 邀请加入
    public function getInvite()
    {
        $roles = Role::where('app_id', Session::get('current_app_id'))->where('name', '<>', 'developer')->get(array('id', 'title'));
        return view('admin.user.invite')->withRoles($roles);
    }

    public function postInvite(Request $request)
    {
        $this->validate($request, array(
            'email' => 'required|email|unique:users',
            'username' => 'required|min:3|unique:users',
            'role_id' => 'required',
        ));

        $token = hash_hmac('sha256', str_random(40), env('APP_KEY'));

        // 写入用户库
        $user = User::create(array('username' => $request->username, 'email' => $request->email));

        // 写入密码重置
        $password_reset = DB::table('password_resets')->insert(array(
            'email' => $request->email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s')
        ));

        // 写入用户角色
        $user_role = UserRole::create(array(
            'app_id' => Session::get('current_app_id'),
            'user_id' => $user->id,
            'role_id' => $request->role_id,
        ));

        // 发送邀请邮件
        $mail = Queue::push(new SendEmail('invite', '邀请入驻用户中心', $token, $request->email));

        if ($user && $password_reset && $user_role && $mail) {
            session()->flash('success_message', '用户邀请成功');
            return Redirect::to('/admin/user/invite');
        } else {
            return Redirect::back()->withInput()->withErrors('保存失败！');
        }
    }

    public function store()
    {
        //
    }

    public function show($id)
    {
        return view('admin.user.show');
    }

    public function edit($id)
    {
        $dispatcher = app('Dingo\Api\Dispatcher');
        $data = $dispatcher->get('api/user/user_info?user_id=1000&access_token=iblRrfFdctRVIxsuTzPDx5TgbGiAobhxjKItRPzO');
        return view('admin.user.edit')->withUser($data['data']);
    }

    public function update($id)
    {
    }

    public function destroy($id)
    {
    }

    // 删除
    public function delete()
    {
        DB::beginTransaction();
        try {
            $ids = $_POST['ids'];
            if (in_array(Auth::id(), $ids)) {
                return Api::jsonReturn(0, '删除失败, 不允许删除自己', array('deleted_num' => 0));
            }
            $result = User::whereIn('id', $ids)->delete();

            DB::commit();
            return Api::jsonReturn(1, '删除成功', array('deleted_num' => $result));
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
            return Api::jsonReturn(0, '删除失败', array('deleted_num' => 0));
        }
    }

    // 从当前应用中移出用户
    public function remove()
    {
        DB::beginTransaction();
        try {
            $ids = $_POST['ids'];
            $app = App::find(Session::get('current_app_id'))->toArray();
            if (in_array($app['user_id'], $ids)) {
                return Api::jsonReturn(0, '移除失败, 不允许移除创建者', array('deleted_num' => 0));
            }
            if (in_array(Auth::id(), $ids)) {
                return Api::jsonReturn(0, '移除失败, 不允许移除自己', array('deleted_num' => 0));
            }
            $result = UserRole::where('app_id', Session::get('current_app_id'))->whereIn('user_id', $ids)->delete();

            DB::commit();
            return Api::jsonReturn(1, '移除成功', array('deleted_num' => $result));
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
            return Api::jsonReturn(0, '移除失败', array('deleted_num' => 0));
        }
    }

    // 当前用户的角色
    public function roles($user_id)
    {
        $roles = Role::where('app_id', Session::get('current_app_id'))->get(array('id', 'name', 'title', 'description', 'updated_at'))->toArray();
        $userRoles = UserRole::where('app_id', Session::get('current_app_id'))->where('user_id', $user_id)->lists('role_id')->toArray();

        foreach ($roles as &$v) {
            if (in_array($v['id'], $userRoles)) {
                $v['checked'] = 1;
            } else {
                $v['checked'] = 0;
            }
        }

        return Api::jsonReturn(1, '获取成功', $roles);
    }

    // 勾选或取消勾选角色
    public function selectOrUnselectRole(Request $request, $id, $role_id)
    {
        if (Role::where('id', $role_id)->where('name', 'developer')->exists()) {
            return Api::jsonReturn(0, '开发者角色限制');
        }

        if ($request->type == 'select') {
            $rs = UserRole::create(array(
                'user_id' => $id,
                'role_id' => $role_id,
                'app_id' => Session::get('current_app_id')
            ));
            $type = '选中角色';
        } else {
            $rs = UserRole::where('user_id', $id)
                ->where('role_id', $role_id)
                ->where('app_id', Session::get('current_app_id'))
                ->delete();
            $type = '移除角色';
        }

        return empty($rs) ? Api::jsonReturn(0, $type . '失败') : Api::jsonReturn(1, $type . '成功');
    }
}
