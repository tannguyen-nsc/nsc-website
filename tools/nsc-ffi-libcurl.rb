# frozen_string_literal: true
# Loaded via RUBYOPT before ethon loads libcurl (FFI does not search PATH on Windows the same way).
# Requires: tools/wpscan-curl-bin/ from MSYS2 UCRT64 (copy *.dll from RubyInstaller MSYS2 ucrt64\bin).

require 'ffi'

libdir = File.expand_path('wpscan-curl-bin', __dir__)
LIBCURL_DLL = File.join(libdir, 'libcurl-4.dll')
return unless File.file?(LIBCURL_DLL)

module NSCFFILibcurl
  def ffi_lib(*names)
    flat = names.flatten.map(&:to_s)
    if flat.any? { |n| n == 'libcurl' || n == 'libcurl.so.4' }
      super(LIBCURL_DLL)
    else
      super
    end
  end
end

FFI::Library.prepend(NSCFFILibcurl)
