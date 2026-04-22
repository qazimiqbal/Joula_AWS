<?php
//include connection file 
include_once("connection.php");
//$sql = "SELECT * FROM `Addresses` limit 1,20 ";


//Start
$total_pages = mysqli_query($conn, 'SELECT * FROM Addresses')->num_rows;

// Check if the page number is specified and check if it's a number, if not return the default page number which is 1.
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
echo $page;
$num_results_on_page = 5;
echo "Hello";


$query = "SELECT Name FROM Addresses  ORDER BY Name LIMIT 5";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $continent);

$continentList = array('Europe', 'Africa', 'Asia', 'North America');

foreach ($continentList as $continent) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        foreach ($row as $r) {
            print "$r ";
        }
        print "\n";
    }
}





if ($stmt = $conn->prepare('SELECT * FROM Addresses  ORDER BY Name LIMIT ?,?')) {
    echo "Hello2";
// Calculate the page to get the results we need from our table.
$calc_page = ($page - 1) * $num_results_on_page;
$stmt->bind_Param('ii', $calc_page, $num_results_on_page);
    echo "Hello3";
$stmt->execute();
    echo "Hello4";

// Get the results...
$result = $stmt->get_result();
echo "Hello5";


//End


//$queryRecords = mysqli_query($conn, $sql) or die("error to fetch employees data");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script type="text/javascript" src="jquery-1.11.1.min.js"></script>
<link rel="stylesheet" type="text/css" href="bootstrap.min.css"/>
<title>phpflow.com : Simple Example of In-line Editing with HTML5,PHP and MySQL</title>
    <style>
        html {
            font-family: Tahoma, Geneva, sans-serif;
            padding: 20px;
            background-color: #F8F9F9;
        }
        table {
            border-collapse: collapse;
            width: 500px;
        }
        td, th {
            padding: 10px;
        }
        th {
            background-color: #54585d;
            color: #ffffff;
            font-weight: bold;
            font-size: 13px;
            border: 1px solid #54585d;
        }
        td {
            color: #636363;
            border: 1px solid #dddfe1;
        }
        tr {
            background-color: #f9fafb;
        }
        tr:nth-child(odd) {
            background-color: #ffffff;
        }
        .pagination {
            list-style-type: none;
            padding: 10px 0;
            display: inline-flex;
            justify-content: space-between;
            box-sizing: border-box;
        }
        .pagination li {
            box-sizing: border-box;
            padding-right: 10px;
        }
        .pagination li a {
            box-sizing: border-box;
            background-color: #e2e6e6;
            padding: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            color: #616872;
            border-radius: 4px;
        }
        .pagination li a:hover {
            background-color: #d4dada;
        }
        .pagination .next a, .pagination .prev a {
            text-transform: uppercase;
            font-size: 12px;
        }
        .pagination .currentpage a {
            background-color: #518acb;
            color: #fff;
        }
        .pagination .currentpage a:hover {
            background-color: #518acb;
        }
    </style>
</head>
<body>
<div class="container" style="padding:50px 250px;">
<h1>Simple Example of Inline Editing with HTML5,PHP and MySQL</h1>
<div id="msg" class="alert"></div>
<table id="employee_grid" class="table table-condensed table-hover table-striped bootgrid-table" width="60%" cellspacing="0">
   <thead>
      <tr>
         <th>ID</th>
         <th>Name</th>
         <th>Halaqa</th>
          <th>Locality</th>
      </tr>
   </thead>
   <tbody id="_editable_table">

      <?php while ($row = $result->fetch_assoc()): ?>

      <tr data-row-id="<?php echo $row['ID'];?>">
         <td class="editable-col" contenteditable="true" col-index='0' oldVal ="<?php echo $row['ID'];?>"><?php echo $row['ID'];?></td>
         <td class="editable-col" contenteditable="true" col-index='1' oldVal ="<?php echo $row['Name'];?>"><?php echo $row['Name'];?></td>
         <td class="editable-col" contenteditable="true" col-index='2' oldVal ="<?php echo $row['Halaqa'];?>"><?php echo $row['Halaqa'];?></td>
         <td class="editable-col" contenteditable="true" col-index='3' oldVal ="<?php echo $row['Locality'];?>"><?php echo $row['Locality'];?></td>
      </tr>
	  <?php endwhile;?>
   </tbody>
</table>


<!--    START-->
    <?php if (ceil($total_pages / $num_results_on_page) > 0): ?>
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="prev"><a href="index.php?page=<?php echo $page-1 ?>">Prev</a></li>
            <?php endif; ?>

            <?php if ($page > 3): ?>
                <li class="start"><a href="index.php?page=1">1</a></li>
                <li class="dots">...</li>
            <?php endif; ?>

            <?php if ($page-2 > 0): ?><li class="page"><a href="index.php?page=<?php echo $page-2 ?>"><?php echo $page-2 ?></a></li><?php endif; ?>
            <?php if ($page-1 > 0): ?><li class="page"><a href="index.php?page=<?php echo $page-1 ?>"><?php echo $page-1 ?></a></li><?php endif; ?>

            <li class="currentpage"><a href="index.php?page=<?php echo $page ?>"><?php echo $page ?></a></li>

            <?php if ($page+1 < ceil($total_pages / $num_results_on_page)+1): ?><li class="page"><a href="index.php?page=<?php echo $page+1 ?>"><?php echo $page+1 ?></a></li><?php endif; ?>
            <?php if ($page+2 < ceil($total_pages / $num_results_on_page)+1): ?><li class="page"><a href="index.php?page=<?php echo $page+2 ?>"><?php echo $page+2 ?></a></li><?php endif; ?>

            <?php if ($page < ceil($total_pages / $num_results_on_page)-2): ?>
                <li class="dots">...</li>
                <li class="end"><a href="index.php?page=<?php echo ceil($total_pages / $num_results_on_page) ?>"><?php echo ceil($total_pages / $num_results_on_page) ?></a></li>
            <?php endif; ?>

            <?php if ($page < ceil($total_pages / $num_results_on_page)): ?>
                <li class="next"><a href="index.php?page=<?php echo $page+1 ?>">Next</a></li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
<!--    END-->


</div>
</body>
</html>
<?php
    $stmt->close();
}
?>
<script type="text/javascript">
$(document).ready(function(){
	$('td.editable-col').on('focusout', function() {
		data = {};
		data['val'] = $(this).text();
		data['id'] = $(this).parent('tr').attr('data-row-id');
		data['index'] = $(this).attr('col-index');
	    if($(this).attr('oldVal') === data['val'])
		return false;

        //alert(data['val']+" - "+data['id']+" - "+data['index']);

		$.ajax({   
				  
					type: "POST",  
					url: "server.php",  
					cache:false,  
					data: data,
					dataType: "json",				
					success: function(response)  
					{   
						//$("#loading").hide();
						if(!response.error) {
							$("#msg").removeClass('alert-danger');
							$("#msg").addClass('alert-success').html(response.msg);
						} else {
							$("#msg").removeClass('alert-success');
							$("#msg").addClass('alert-danger').html(response.msg);
						}
					}   
				});
	});
});

</script>