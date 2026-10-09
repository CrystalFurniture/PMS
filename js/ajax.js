// Add Product DP
     $('.add_dp').click(function(){  
           var product_id = $(this).attr("id");  
           var action = "add_dp";  
		   
		   cache: false;
		   
		   //alert(product_id);
           $.ajax({  
                     url:"action.php",  
                     method:"POST",  
                     dataType: "html",  
                     data:{  
                          product_id	:	product_id,   
                          action		:	action  
                     },  
                     success:function(data)  
                     {  
                          //$("#messages").load(" #messages");
						  //$("#messages").html(data);
						   $('#messages').html(data)
						  
						  console.log(data);
                          
                     }  
                });  
          
          
      });  

// Add Product MRP
     $('.add_mrp').click(function(){  
           var product_id = $(this).attr("id");  
           var action = "add_mrp";  
		   
		   cache: false;
		   
		   //alert(product_id);
           $.ajax({  
                     url:"action.php",  
                     method:"POST",  
                     dataType: "html",  
                     data:{  
                          product_id	:	product_id,   
                          action		:	action  
                     },  
                     success:function(data)  
                     {  
                          //$("#messages").load(" #messages");
						  //$("#messages").html(data);
						   $('#messages').html(data)
						  
						  console.log(data);
                          
                     }  
                });  
          
          
      });  

// Quantity Change DP


	$(document).on('change', '.quantityDP', function(){  
           var id = $(this).data("product_id");  
           var quantity = $(this).val();  
           var action = "quantityDP_change";  
		  
		  
           if(quantity != '')  
           {  
                $.ajax({  
                     url :"action.php",  
                     method:"POST",  
                     dataType:"json",  
                     data:{
						 id			:	id,
						 quantity	:	quantity,
						 action		:	action
						 },  
                     success:function(data){  
						$('#order_table').html(data.order_table); 
                         
                          //$('.badge').text(data.cart_item);  
                     }  
                });  
           }  
      });  
	  
	  $(document).on('click', '.delete', function(){  
           var id = $(this).attr("id");  
           var action = "removeDP";  
           if(confirm("Are you sure you want to remove this product?"))  
           {  
                $.ajax({  
                     url:"action.php",  
                     method:"POST",  
                     dataType:"json",  
                     data:{	id		:	id,
							action	:	action},  
                     success:function(data){  
                          $('#order_table').html(data.order_table);  
                          //$('.badge').text(data.cart_item);  
                     }  
                });  
           }  
           else  
           {  
                return false;  
           }  
      }); 


	  
// Quantity Change MRP

	$(document).on('change', '.quantityMRP', function(){  
           var id = $(this).data("product_id");  
           var quantity = $(this).val();  
           var action = "quantityMRP_change";  
		  
		  
           if(quantity != '')  
           {  
                $.ajax({  
                     url :"action.php",  
                     method:"POST",  
                     dataType:"json",  
                     data:{
						 id			:	id,
						 quantity	:	quantity,
						 action		:	action
						 },  
                     success:function(data){  
						$('#order_tableMRP').html(data.order_tableMRP); 
                         
                          //$('.badge').text(data.cart_item);  
                     }  
                });  
           }  
      });  
	  
	  $(document).on('click', '.deleteMRP', function(){  
           var id = $(this).attr("id");  
           var action = "removeMRP";  
           if(confirm("Are you sure you want to remove this product?"))  
           {  
                $.ajax({  
                     url:"action.php",  
                     method:"POST",  
                     dataType:"json",  
                     data:{	id		:	id,
							action	:	action},  
                     success:function(data){  
                          $('#order_tableMRP').html(data.order_tableMRP);  
                          //$('.badge').text(data.cart_item);  
                     }  
                });  
           }  
           else  
           {  
                return false;  
           }  
      });  
