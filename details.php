<?php 
	session_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="http://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
<style type="text/css">
	.box{
		padding:8px;
		border:1px solid blue;
		margin-bottom:8px;
		width:300px;
		height:100px;
	}
	.newbox{
		padding:8px;
		border:1px solid black;
		margin-bottom:8px;
		width:95%;
		#height:50px;
	}
</style>

</head>
<body>


<div data-role="page" id="pageone" data-theme="a" data-position="fixed" >
	<div data-role="header"  data-add-back-btn="true" data-theme="b" data-position="fixed" >
    <h1>Masjid Details</h1>

  </div>
  <div data-role="main" id="content1" class="ui-content">
	<div id="here_table"> </div>
	<script>
		$(document).ready(function(){
			//$Verified."*".$No_Male."*".$No_Female."*".$No_Children."*".$Area."*".$Zone."*".$Comments."*".$Last_Visit
			$.getJSON('getdetails.php?id=<?php echo $_GET['id']; ?>', function(data) {
                /* data will hold the php array as a javascript object */
				//alert(data);
				var strs = data.split("*")
				var ID = strs[0];
				var Name = strs[1];
				var Halaqa = strs[2];
				var Address = strs[3];
				var Verified = strs[4];
				var No_Male = strs[5];
				var No_Female = strs[6];
				var No_Children = strs[7];
				var Area = strs[8];
				var Zone = strs[9];
				var Comments = strs[10];
				var Last_Visit = strs[11];
				
				$('#here_table').append(  '<table>' );
				$('#here_table').append( '<tr><td>Name = </td><td>' + Name + '</td></tr>' );
				$('#here_table').append( '<tr><td>Halaqa = </td><td>' + Halaqa + '</td></tr>' );
				$('#here_table').append( "<tr><td>Address = </td><td><a href='http://maps.google.com/?q="+Address+"'>" + Address + "</a></td></tr>" );
				$('#here_table').append( '<tr><td>Number of Male = </td><td>' + No_Male + '</td></tr>' );
				$('#here_table').append( '<tr><td>Number of Female = </td><td>' + No_Female + '</td></tr>' );
				$('#here_table').append( '<tr><td>Number of Children = </td><td>' + No_Children + '</td></tr>' );
				$('#here_table').append( '<tr><td>Area = </td><td>' + Area + '</td></tr>' );
				$('#here_table').append( '<tr><td>Zone = </td><td>' + Zone + '</td></tr>' );
				$('#here_table').append( '<tr><td>Comments = </td><td>' + Comments + '</td></tr>' );
				$('#here_table').append( '<tr><td>Last Visit = </td><td>' + Last_Visit + '</td></tr>' );
				<?php
					if($_SESSION['permissions_level'] > 0){
						?>
							$('#here_table').append( "<tr><td></td><td><a href='http://"+window.location.host+"/mobile/edit.php?id="+ID+"'>Edit</a></td></tr>" );
						<?php
					}				
				?>

				 $('#here_table').append(  '</table>' );
				

			});
			
			
			
			
		}); //document.ready
	</script>
	</div>
	<div data-role="footer" data-theme="b" data-position="fixed" >
		<h1>Footer Text</h1>
	</div>
</div> 



</body>
</html>