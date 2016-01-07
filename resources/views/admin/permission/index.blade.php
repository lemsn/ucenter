@extends('admin.base')

@section('content')
<div class="row">
<div class="col-lg-12">
<div class="panel panel-default">
	<div class="panel-heading">用户管理</div>
	<div class="panel-body">
		<div class="dataTable_wrapper">
			<br />
			<div class="input-group custom-search-form">
				<a href="{{ URL('admin/user/invite') }}" class="btn btn-primary">邀请</a>
				&nbsp;
				<a href="{{ URL('admin/user/create') }}" class="btn btn-primary">新增</a>
				&nbsp;
				<a href='javascript:void(0);' class="btn btn-primary btn-danger" onclick='return check_delete();'>删除</a>
				<input type="text" id="search" class="form-control search" placeholder="搜索">
				<span class="input-group-btn">
					<button class="btn btn-default" type="button">
						<i class="fa fa-search"></i>
					</button>
				</span>
			</div>
			<form class="form-horizontal" role="form" method="POST" action="{{ url('/admin/app') }}">
				<input type="hidden" name="_token" value="{{ csrf_token() }}">
				<table class="table table-striped table-bordered table-hover" id="user_index" class="display" cellspacing="0" width="100%" border='0px'>
					<thead>
						<tr>
							<td style="width:15px"><input class="checkbox" type="checkbox" name="id" id='checkAll'></td>
							<td>用户名</td>
							<td>邮箱</td>
							<td>手机</td>
							<td>创建时间</td>
							<td>更新时间</td>
							<td>操作</td>
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</form>
		</div>
		<!-- /.dataTable_wrapper -->
	</div>
	<!-- /.panel-body -->
</div>
<!-- /.panel -->
</div>
</div>

<script>
var datatable_id = 'user_index';
var columnDefs_targets = [0, 6];
var order = [5, 'desc'];
var ajax_url = '/admin/role/lists';
var delete_url = '/admin/role/delete';
var columns = [{
					"data": "id",
					"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
						$(nTd).html("<input type='checkbox' id='" + sData + "' class='checkbox' name='ids' value='" + sData + "'>");
					}
				},
				{"data": "username"},
				{"data": "email"},
				{"data": "phone"},
				{"data": "created_at"},
				{"data": "updated_at"},
				{
					"data": "id",
					"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
						$(nTd).html("<a href=/admin/user/" + sData + "/edit>编辑</a>" + " " +
							"<a href='javascript:void(0);' onclick='return check_delete(" + sData + ");'>删除</a>");
					}
				}];
</script>
@endsection