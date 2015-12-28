<!DOCTYPE html>
<html lang="en">
  <head>
    <title>/v/GAs - Voting</title>

    
    <link rel="stylesheet" href="/jquery/jquery-ui-1.9.2.min.css">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/voting.css">
    
    <script src='/jquery/jquery-1.8.2.min.js'></script>
    <script src='/jquery/jquery-ui-1.9.2.min.js'></script>
	<script src="/jquery/jquery.ui.touch-punch.min.js"></script>
    <script src='/bootstrap-2.1.0/js/bootstrap.min.js'></script>
    <script src='/dumbshit.js'></script>
    <script src='/voting.js'></script>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8" />
    
    <script type="text/javascript">
      <if:votingEnabled>
      var votingEnabled = true;
      <else:votingEnabled>
      var votingEnabled = false;
      </if:votingEnabled>

      <if:category>
      var lastVotes = <tag:lastVotes />;
      var votesChanged = false;
      var previousLockExists = lastVotes.length > 1;
      var currentCategory = "<tag:category.ID />";
      </if:category>
    </script>

    <if:votingEnabled>
    <style type="text/css">
      .aNominee {
        cursor: move;
      }
    </style>
    </if:votingEnabled>

</head>
<body>


<header>
	<a href="/voting"><img src="/2014voting/2014logo.png" id="thelogo" alt="oh god it's happening again" /></a>
	<h1>The 2014 Vidya Gaem Awards</h1>
	<h2><tag:voteText /></h2>
</header>

<div id="wrapper">      
  <if:category>
  <div class="awardHeader">
    <a href="/voting/<tag:prevCategory />" class="navigation left"></a>
    <div class="awardHeaderContainer">
      <div class="awardName"><tag:category.Name /></div>
      <h2 class="awardSubtitle"><tag:category.Subtitle /></h2>
    </div>
    <a href="/voting/<tag:nextCategory />" class="navigation right"></a>
  </div>
  <if:votingEnabled>
  <img src="/2014voting/dont_forget.png" id="dontforget" alt="Don't forget to hit submit" />
  </if:votingEnabled>
  </if:category>
    
<if:category>
<div id="limitsDrag"> 
    <div id="nomineeColumn" class="column">
        
        <img src="/2014voting/topNominees.png" width="267px" height="105px" alt="Pick your nominees" />

        <loop:nominees>

        <div class="voteBox"><div id="nominee-<tag:nominees[].NomineeID />" class="aNominee" data-order="<tag:nominees[].Order />" data-nominee="<tag:nominees[].NomineeID />">
            <img class="fakeBorder" src="/2014voting/votebox_foreground.png">
            <img class="fakeBorder locked" src="/2014voting/votebox_foreground_locked.png">
			<div class="info">
				<div class="innerInfo"><tag:nominees[].FlavorText /></div>
			</div>
            <img class="nomineeImage" src="<tag:nominees[].Image />">
            <div class="nomineeInfo">
                <div class="number"></div>
                <div class="nomineeName"><tag:nominees[].Name /></div>
                <div class="nomineeSubtitle"><tag:nominees[].Subtitle /></div>
            </div>
        </div></div>

        </loop:nominees>

    </div>

    <div id="spacerColumn" class="column">
      &nbsp;
    </div>
    
    <!if:votingNotYetOpen>
    <div id="voteColumn" class="column">

        <img src="/2014voting/topVotes.png" width="267px" height="105px" alt="Drag and drop to vote"/>
        
        <loop:dumbloop>
        <div id="voteBox<tag:dumbloop[] />" class="voteBox">
        </div>
        </loop:dumbloop>
        
       
    </div>
    </!if:votingNotYetOpen>

</div>

 <if:votingEnabled>
	<footer style='position: relative; clear: both;'>
		<div id="btnResetVotes" class="btnSubmit" alt="Reset Votes"></div>
		<span id="votesAreNotLocked">
			<div id="btnLockVotes" class="btnSubmit" alt="Submit Votes"></div>
		</span>
		<span id="votesAreLocked" style="display: none;">
			<div id="btnLockVotes" class="btnSubmit iVoted" alt="Saved!"></div>
		</span>
    <a href="/voting/<tag:nextCategory />" class="navigation right" alt="Next category"></a>
	</footer>
</if:votingEnabled>

<else:category>
<div id="startMessage">

  <if:votingNotYetOpen>
  <!-- Before votes open -->
	  <h2>How to vote:</h2>
	  <p>Despite the new look, voting is the same as last year. Vote for as many nominees as you want, and put them in the order you'd like to see them win. Too much effort for you? Vote for one nominee and call it a day.</p>
	  <p>Voting isn't open yet, but you can still browse the awards and have a look at the nominees. You can use the list of awards at the bottom and the meme arrows at the top to navigate.</p>
  </if:votingNotYetOpen>

  <if:votingEnabled>
  <!-- While votes are open -->
	  <h2>How to vote:</h2>
	  <p>Despite the new look, voting is still the same. Vote for as many nominees as you want, and put them in the order you'd like to see them win. Too much effort for you? Vote for one nominee and call it a day.</p>
	  <p>You can use the award list at the bottom to navigate, or just use the arrows that appear after you click submit.</p>
	  
	  <a href="/voting/most-hated-game" id="btnStart"></a>
  </if:votingEnabled>

  <if:votingConcluded>
  <!-- After votes close -->
  <h2>Thanks to everybody who voted.</h2>
  <p>No new votes can be made, but if you've already voted you can still see the votes you made.</p>
  </if:votingConcluded>

  <h2>Stream information:</h2>
  <p>We plan to stream earlier than previous years (at the end of January).</p><p>Don't forget to try and enter the skit contest, winner will get $25. Check out <a href="https://www.youtube.com/watch?v=Wc0nOBMUuwQ">this video</a> for more details.</p>

</div>
</if:category>

<img src="/dumb.gif" alt="" class="shit">
</div>

<div id="containerCategories">
    <h1 id="topCategories">
        The Awards
    </h1>
    
    <loop:categories>
    <a href="/voting/<tag:categories[].ID />" id="<tag:categories[].ID />" class="category <if:categories[].Active>active</if:categories[].Active> <if:categories[].Completed>complete</if:categories[].Completed>">
        <h3><tag:categories[].Name /></h3>
        <p><tag:categories[].Subtitle /></p>
    </a>
    </loop:categories>

    <if:loggedIn>
  <h3 style='clear:both; padding-top: 50px;'><a href="/home" style="color: #f2ff1a;">Back to the main part of the site</a></h3>
  </if:loggedIn>
</div> 
 
</body>
</html>
