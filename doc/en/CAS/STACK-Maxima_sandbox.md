# STACK - Maxima sandbox

It is very useful when authoring questions to be able to test out Maxima code in the same environment in which STACK uses [Maxima](Maxima.md).
That is to say, to run a desktop version of Maxima with the local settings and STACK specific functions loaded.
To do this you will need to load your local settings, and also the libraries of Maxima functions specific to STACK.

For example, many of the functions are defined in
~~~~~~~~~
		/maxima/stackmaxima.mac
~~~~~~~~~
  
These instructions work on a Microsoft platform.

### Setting Maxima's Path ###

Setting the path in Maxima is a problem on a Microsoft platform.  Maxima does not deal well with spaces in filenames, for example.  The simplest solution is to create a directory

c:\maxima

and add this to Maxima's path.  Place all Maxima files in this directory, so they will then be seen by Maxima.
For Maxima 5.22.1, edit, or create, the file 

	C:\Program Files\Maxima-5.22.1\share\maxima\5.22.1\share\maxima-init.mac

ensure it contains the line

	file_search_maxima:append( file_search_maxima, [sconcat("C:/maxima/###.{mac,mc}")] )$

Other versions of Maxima are similar.

### Loading STACK's functions ###

STACK automatically adjusts Maxima's path and loads a number of files.
These create new, STACK specific functions and reflects your local settings.
To do this, STACK loads a file which is automatically created at install time.
Look in `config.php` for the line

	$this->configArray["logs"] = ... 

For example, the value might look like

	C:/stacklocal/

Once STACK is fully installed, inside this directory is the file `maximalocal.mac`.
You need to load this file into Maxima to recreate the setup of Maxima as seen by STACK.
Assuming you have created a directory `c:\maxima` as suggested above and added it to Maxima's path, the simplest way to do this is to create a file
	
	c:\maxima\sm.mac

and into this file add the line

	load("C:/stacklocal/maximalocal.mac");

To load this into STACK simply type

	load(sm);

at Maxima's command line.
The time spent setting the path in this way is soon repaid in not having to type the following line each time you want the sandbox.
Your path to `maximalocal.mac` might be significantly longer....!

	load("C:/stacklocal/maximalocal.mac");

You will know the file is loaded correctly if you see a message such as the following

	(%i1) load(sm);
    Loading maxima-grobner $Revision: 1.6 $ $Date: 2009/06/02 07:49:49 $
	[ Stack-Maxima started V2.3 ] 
	(%o0) "C:/maxima/sm.mac"

You can test this out by using, for example, the `rand()` function.

	rand(matrix([5,5],[5,5]));

to create a pseudo-random matrix.
