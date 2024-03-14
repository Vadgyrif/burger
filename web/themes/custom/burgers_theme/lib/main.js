jQuery(document).ready(function($) {


  console.log('helllooooo');
    
    $('.views-col').mouseenter(function () { 

        $(this).addClass('open');
      
      });
      
      $('.expanded.dropdown').mouseleave(function () { 
      
        $(this).removeClass('open');
        
      });
      


});