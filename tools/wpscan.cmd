@echo off
REM Run WPScan on Windows: user gem install + bundled libcurl DLLs + FFI hook.
REM If libcurl fails: copy all *.dll from C:\Ruby33-x64\msys64\ucrt64\bin into tools\wpscan-curl-bin\
REM Usage: tools\wpscan.cmd --version
REM        tools\wpscan.cmd --url=https://example.com/
setlocal EnableExtensions
if not exist "%~dp0wpscan-curl-bin\libcurl-4.dll" (
  echo Missing tools\wpscan-curl-bin\libcurl-4.dll — copy *.dll from C:\Ruby33-x64\msys64\ucrt64\bin 1>&2
  exit /b 1
)
set "PATH=%~dp0wpscan-curl-bin;C:\Ruby33-x64\bin;%PATH%"
set "RUBYOPT=-r%~dp0nsc-ffi-libcurl.rb"
set "GEM_HOME=%USERPROFILE%\.local\share\gem\ruby\3.3.0"
set "GEM_PATH=%GEM_HOME%"
set "RUBY_EXE=C:\Ruby33-x64\bin\ruby.exe"
if not exist "%RUBY_EXE%" for /f "delims=" %%i in ('where ruby 2^>nul') do set "RUBY_EXE=%%i"
if not exist "%RUBY_EXE%" (
  echo Edit tools\wpscan.cmd: set RUBY_EXE= to your ruby.exe path. 1>&2
  exit /b 1
)
set "WPSCAN_RB="
for /d %%d in ("%GEM_HOME%\gems\wpscan-*") do (
  if exist "%%d\bin\wpscan" set "WPSCAN_RB=%%d\bin\wpscan"
)
if not defined WPSCAN_RB (
  echo WPScan gem not found. Run: gem install wpscan --no-document 1>&2
  exit /b 1
)
"%RUBY_EXE%" "%WPSCAN_RB%" %*
exit /b %ERRORLEVEL%
