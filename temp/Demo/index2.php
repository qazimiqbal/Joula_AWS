<?php

?>
<html>
	<head>
		<title>Data Report for My Joulax</title>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" />
		<script src="https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js"></script>
		<script src="https://cdn.datatables.net/1.10.12/js/dataTables.bootstrap.min.js"></script>  
		<link rel="stylesheet" href="https://cdn.datatables.net/1.10.12/css/dataTables.bootstrap.min.css" />
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
		<link href="https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet">
  		<script src="https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.1/bootstrap3-editable/js/bootstrap-editable.js"></script>
	</head>
	<body>
		<div class="container">
			<h3 align="center">My Joula Data </h3>
			<br />
			<div class="panel panel-default">
				<div class="panel-heading">Data</div>
				<div align="center">  
                     <button name="create_excel" id="create_excel" class="btn btn-success">Create Excel File</button>  
                </div>
				<div class="panel-body">
					<div class="table-responsive" id="joula_table">
						<table id="sample_data" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>ID</th>
									<th>Name</th>
									<th>Halaqa</th>
									<th>H No</th>
                                    <th>Apt No</th>
                                    <th>St_Name</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>Zip</th>
                                    <th>Ethinicity</th>
                                    <th>Coordinates</th>
								</tr>
							</thead>
						</table>
					</div>
				</div>
			</div>
		</div>
		<br />
		<br />
	</body>
</html>

<script type="text/javascript" language="javascript">

$(document).ready(function(){


	var dataTable = $('#sample_data').DataTable({
		"processing": true,
		"serverSide": true,
		"order":[],
		"ajax":{
			url:"fetch2.php",
			type:"POST",
		},
		createdRow:function(row, data, rowIndex)
		{
			$.each($('td', row), function(colIndex){
				if(colIndex == 1)
				{
					$(this).attr('data-name', 'Name');
					$(this).attr('class', 'Name');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
				if(colIndex == 2)
				{
					$(this).attr('data-name', 'Halaqa');
					$(this).attr('class', 'Halaqa');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
				if(colIndex == 3)
				{
					$(this).attr('data-name', 'H_No');
					$(this).attr('class', 'H_No');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 4)
				{
					$(this).attr('data-name', 'Apt_No');
					$(this).attr('class', 'Apt_No');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 5)
				{
					$(this).attr('data-name', 'St_Name');
					$(this).attr('class', 'St_Name');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 6)
				{
					$(this).attr('data-name', 'City');
					$(this).attr('class', 'City');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 7)
				{
					$(this).attr('data-name', 'State');
					$(this).attr('class', 'State');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 8)
				{
					$(this).attr('data-name', 'Zip');
					$(this).attr('class', 'Zip');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 9)
				{
					$(this).attr('data-name', 'Ethinicity');
					$(this).attr('class', 'Ethinicity');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
                if(colIndex == 10)
				{
					$(this).attr('data-name', 'Coordinates');
					$(this).attr('class', 'Coordinates');
					$(this).attr('data-type', 'text');
					$(this).attr('data-pk', data[0]);
				}
			});
		}
	});

	$('#sample_data').editable({
		container:'body',
		selector:'td.Name',
		url:'update2.php',
		title:'Name',
		type:'POST',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});

	$('#sample_data').editable({
		container:'body',
		selector:'td.Halaqa',
		url:'update2.php',
		title:'Halaqa',
		type:'POST',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});

	$('#sample_data').editable({
		container:'body',
		selector:'td.H_No',
		url:'update2.php',
		title:'H_No',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.Apt_No',
		url:'update2.php',
		title:'Apt_No',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.St_Name',
		url:'update2.php',
		title:'St_Name',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.City',
		url:'update2.php',
		title:'City',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.State',
		url:'update2.php',
		title:'State',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.Zip',
		url:'update2.php',
		title:'Zip',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.Ethinicity',
		url:'update2.php',
		title:'Ethinicity',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
    $('#sample_data').editable({
		container:'body',
		selector:'td.Coordinates',
		url:'update2.php',
		title:'Coordinates',
		type:'POST',
		datatype:'json',
		validate:function(value){
			if($.trim(value) == '')
			{
				return 'This field is required';
			}
		}
	});
	

	$('#create_excel').click(function(){  
		//alert("Click");
        var excel_data = $('#sample_data').html();  
		// var page = "excel.php?data=" + excel_data; 
        var page = "excel.php"; 
		//alert(page); 
        window.location = page;  
      });  
});	
</script>